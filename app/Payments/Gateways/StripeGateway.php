<?php

namespace App\Payments\Gateways;

use App\Models\Booking;
use App\Models\Payment;
use App\Payments\Checkout;
use App\Payments\CheckoutLinks;
use App\Payments\GatewayError;
use App\Payments\GatewayState;
use App\Payments\GatewayStatus;
use App\Payments\InvalidWebhook;
use App\Payments\PaymentGateway;
use App\Payments\WebhookEvent;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Stripe Checkout, with the money authorised on the card first
 * (capture_method=manual) and captured once the booking is confirmed.
 *
 * Called over plain HTTP rather than through Stripe's SDK: one dependency
 * fewer, and simple to fake in tests. The API version is pinned, and every
 * call that moves money carries an idempotency key.
 */
class StripeGateway implements PaymentGateway
{
    private const API = 'https://api.stripe.com/v1/';

    /** Stripe won't let a Checkout Session expire sooner than this. */
    private const SESSION_MINUTES = 31;

    /** How old a webhook can be before it's refused as a replay. */
    private const WEBHOOK_TOLERANCE_SECONDS = 300;

    public function key(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return 'Card';
    }

    public function isConfigured(): bool
    {
        return filled(config('payments.gateways.stripe.secret'));
    }

    public function isTestMode(): bool
    {
        $secret = (string) config('payments.gateways.stripe.secret');

        return str_starts_with($secret, 'sk_test_') || str_starts_with($secret, 'rk_test_');
    }

    public function supports(string $currency): bool
    {
        return in_array(strtoupper($currency), (array) config('payments.gateways.stripe.currencies'), true);
    }

    public function begin(Payment $payment, Booking $booking, CheckoutLinks $links): Checkout
    {
        $booking->loadMissing(['business', 'customer']);
        $start = $booking->starts_at->setTimezone($booking->business->timezone);

        $session = $this->call(fn () => $this->http('halfpast-checkout-'.$payment->id)->post('checkout/sessions', [
            'mode' => 'payment',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($payment->currency),
                    'unit_amount' => $payment->amount,
                    'product_data' => [
                        'name' => "Deposit: {$booking->service_name}",
                        'description' => "{$booking->business->name}, {$start->format('D j M, g:i a')}",
                    ],
                ],
            ]],
            // Cards can be authorised now and captured later.
            'payment_method_types' => ['card'],
            'payment_intent_data' => [
                'capture_method' => 'manual',
                'metadata' => ['payment_id' => $payment->id, 'booking' => $booking->code],
            ],
            'customer_email' => $booking->customer?->email,
            'client_reference_id' => (string) $payment->id,
            'metadata' => ['payment_id' => $payment->id, 'booking' => $booking->code],
            'success_url' => $links->return,
            'cancel_url' => $links->cancel,
            'expires_at' => now()->addMinutes(self::SESSION_MINUTES)->getTimestamp(),
        ]));

        $payment->update(['gateway_order_id' => $session['id']]);

        return new Checkout(redirectUrl: (string) $session['url']);
    }

    public function status(Payment $payment, array $returned = []): GatewayStatus
    {
        $session = $this->session((string) $payment->gateway_order_id);
        $intent = $session['payment_intent'] ?? null;
        $expired = ($session['status'] ?? null) === 'expired';

        if (! is_array($intent)) {
            return new GatewayStatus(
                state: $expired ? GatewayState::Failed : GatewayState::Pending,
                amount: (int) ($session['amount_total'] ?? 0),
                currency: strtoupper((string) ($session['currency'] ?? '')),
                reason: $expired ? 'expired' : null,
            );
        }

        $state = match ($intent['status'] ?? null) {
            'requires_capture' => GatewayState::Authorized,
            'succeeded' => GatewayState::Captured,
            'canceled' => GatewayState::Failed,
            // A declined card stays here while the customer tries another on Stripe's page.
            default => $expired ? GatewayState::Failed : GatewayState::Pending,
        };

        return new GatewayStatus(
            state: $state,
            paymentId: (string) $intent['id'],
            amount: (int) $intent['amount'],
            currency: strtoupper((string) $intent['currency']),
            reason: $state === GatewayState::Failed ? ($expired ? 'expired' : 'cancelled') : null,
        );
    }

    public function capture(Payment $payment): GatewayStatus
    {
        $intentId = $payment->gateway_payment_id ?? $this->status($payment)->paymentId;

        if ($intentId === null) {
            throw new GatewayError("Stripe payment {$payment->id} has nothing to capture yet.");
        }

        $intent = $this->call(fn () => $this->http()->get("payment_intents/{$intentId}"));

        if (($intent['status'] ?? null) !== 'succeeded') {
            $intent = $this->call(fn () => $this->http('halfpast-capture-'.$payment->id)->post("payment_intents/{$intentId}/capture"));
        }

        return new GatewayStatus(GatewayState::Captured, (string) $intent['id'], (int) $intent['amount'], strtoupper((string) $intent['currency']));
    }

    public function release(Payment $payment): void
    {
        if ($payment->gateway_order_id === null) {
            return;
        }

        $session = $this->session($payment->gateway_order_id);
        $intent = $session['payment_intent'] ?? null;

        // Authorised but not captured: cancel it and the card hold drops.
        if (is_array($intent) && in_array($intent['status'] ?? null, ['requires_capture', 'requires_payment_method', 'requires_confirmation', 'requires_action'], true)) {
            $this->call(fn () => $this->http('halfpast-release-'.$payment->id)->post("payment_intents/{$intent['id']}/cancel"));

            return;
        }

        // Not paid yet: close the checkout so it can't be paid any more.
        if (($session['status'] ?? null) === 'open') {
            $this->call(fn () => $this->http()->post("checkout/sessions/{$payment->gateway_order_id}/expire"));
        }
    }

    public function refund(Payment $payment, int $amount, string $idempotencyKey): string
    {
        if ($payment->gateway_payment_id === null) {
            throw new GatewayError("Stripe payment {$payment->id} has nothing to refund.");
        }

        $refund = $this->call(fn () => $this->http($idempotencyKey)->post('refunds', [
            'payment_intent' => $payment->gateway_payment_id,
            'amount' => $amount,
            'metadata' => ['payment_id' => $payment->id],
        ]));

        return (string) $refund['id'];
    }

    public function webhook(Request $request): ?WebhookEvent
    {
        $secret = (string) config('payments.gateways.stripe.webhook_secret');

        if ($secret === '') {
            throw new InvalidWebhook('No Stripe webhook secret is set.');
        }

        $payload = $request->getContent();
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', (string) $request->header('Stripe-Signature')) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't') {
                $timestamp = (int) $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || abs(time() - $timestamp) > self::WEBHOOK_TOLERANCE_SECONDS) {
            throw new InvalidWebhook('Stripe webhook is missing its timestamp or is too old.');
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        if (! collect($signatures)->contains(fn (string $signature) => hash_equals($expected, $signature))) {
            throw new InvalidWebhook('Stripe webhook signature does not match.');
        }

        /** @var array{id?: string, type?: string, data?: array{object?: array{id?: string}}} $event */
        $event = (array) json_decode($payload, true);
        $type = (string) ($event['type'] ?? '');

        // Checkout sessions are what our payments are keyed by.
        if (! str_starts_with($type, 'checkout.session.') || ! isset($event['id'], $event['data']['object']['id'])) {
            return null;
        }

        return new WebhookEvent($event['id'], $type, $event['data']['object']['id'], ['type' => $type]);
    }

    /**
     * @return array<string, mixed>
     */
    private function session(string $sessionId): array
    {
        return $this->call(fn () => $this->http()->get("checkout/sessions/{$sessionId}", ['expand' => ['payment_intent']]));
    }

    private function http(?string $idempotencyKey = null): PendingRequest
    {
        $request = Http::baseUrl(self::API)
            ->withBasicAuth((string) config('payments.gateways.stripe.secret'), '')
            ->withHeaders(['Stripe-Version' => (string) config('payments.gateways.stripe.api_version')])
            ->asForm()
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(20);

        return $idempotencyKey === null ? $request : $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
    }

    /**
     * @param  callable(): Response  $request
     * @return array<string, mixed>
     */
    private function call(callable $request): array
    {
        try {
            $response = $request();
        } catch (ConnectionException $e) {
            throw new GatewayError('Stripe could not be reached: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new GatewayError('Stripe refused: '.($response->json('error.message') ?? 'HTTP '.$response->status()));
        }

        return (array) $response->json();
    }
}
