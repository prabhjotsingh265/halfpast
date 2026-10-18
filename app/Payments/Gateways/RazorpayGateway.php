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
 * Razorpay: UPI, cards and netbanking in a pop-up on our own checkout page.
 * Orders are created with automatic capture off, so a payment is only
 * authorised until the booking is confirmed and we capture it.
 */
class RazorpayGateway implements PaymentGateway
{
    private const API = 'https://api.razorpay.com/v1/';

    /** The pop-up closes itself after this long, matching the payment window. */
    private const CHECKOUT_TIMEOUT_SECONDS = 900;

    public function key(): string
    {
        return 'razorpay';
    }

    public function label(): string
    {
        return 'UPI, cards and netbanking';
    }

    public function isConfigured(): bool
    {
        return filled(config('payments.gateways.razorpay.key_id')) && filled(config('payments.gateways.razorpay.key_secret'));
    }

    public function supports(string $currency): bool
    {
        return in_array(strtoupper($currency), (array) config('payments.gateways.razorpay.currencies'), true);
    }

    public function begin(Payment $payment, Booking $booking, CheckoutLinks $links): Checkout
    {
        $booking->loadMissing(['business', 'customer']);

        $order = $this->call(fn () => $this->http()->post('orders', [
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'receipt' => 'payment-'.$payment->id,
            // Authorise only; we capture once the booking is definitely theirs.
            'payment_capture' => 0,
            'notes' => ['payment_id' => (string) $payment->id, 'booking' => $booking->code],
        ]));

        $payment->update(['gateway_order_id' => $order['id']]);

        // Everything the pop-up needs. The secret never leaves the server.
        return new Checkout(widget: [
            'gateway' => 'razorpay',
            'key' => (string) config('payments.gateways.razorpay.key_id'),
            'order_id' => $order['id'],
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'name' => $booking->business->name,
            'description' => "Deposit: {$booking->service_name}",
            'prefill' => array_filter([
                'name' => $booking->customer?->name,
                'email' => $booking->customer?->email,
                'contact' => $booking->customer?->phone,
            ]),
            'notes' => ['booking' => $booking->code],
            'timeout' => self::CHECKOUT_TIMEOUT_SECONDS,
            'return_url' => $links->return,
            'cancel_url' => $links->cancel,
        ]);
    }

    public function status(Payment $payment, array $returned = []): GatewayStatus
    {
        $attempts = (array) ($this->call(fn () => $this->http()->get("orders/{$payment->gateway_order_id}/payments"))['items'] ?? []);

        // A customer can try several methods on one order; the one with money wins.
        foreach (['captured' => GatewayState::Captured, 'authorized' => GatewayState::Authorized] as $status => $state) {
            foreach ($attempts as $attempt) {
                if (($attempt['status'] ?? null) === $status) {
                    return new GatewayStatus($state, (string) $attempt['id'], (int) $attempt['amount'], strtoupper((string) $attempt['currency']));
                }
            }
        }

        $allFailed = $attempts !== [] && collect($attempts)->every(fn ($attempt) => ($attempt['status'] ?? null) === 'failed');

        return new GatewayStatus(
            state: $allFailed ? GatewayState::Failed : GatewayState::Pending,
            reason: $allFailed ? 'declined' : null,
        );
    }

    public function capture(Payment $payment): GatewayStatus
    {
        $paymentId = $payment->gateway_payment_id ?? $this->status($payment)->paymentId;

        if ($paymentId === null) {
            throw new GatewayError("Razorpay payment {$payment->id} has nothing to capture yet.");
        }

        $attempt = $this->call(fn () => $this->http()->get("payments/{$paymentId}"));

        if (($attempt['status'] ?? null) !== 'captured') {
            $attempt = $this->call(fn () => $this->http()->post("payments/{$paymentId}/capture", [
                'amount' => $payment->amount,
                'currency' => $payment->currency,
            ]));
        }

        return new GatewayStatus(GatewayState::Captured, (string) $attempt['id'], (int) $attempt['amount'], strtoupper((string) $attempt['currency']));
    }

    /**
     * Razorpay has no way to cancel an authorisation over the API: a payment
     * that is never captured goes back to the customer automatically within
     * a few days. Unpaid orders simply expire. So there's nothing to send.
     */
    public function release(Payment $payment): void {}

    public function refund(Payment $payment, int $amount, string $idempotencyKey): string
    {
        if ($payment->gateway_payment_id === null) {
            throw new GatewayError("Razorpay payment {$payment->id} has nothing to refund.");
        }

        // Razorpay's idempotency header makes a retried refund count once.
        $refund = $this->call(fn () => $this->http()
            ->withHeaders(['X-Refund-Idempotency' => $idempotencyKey])
            ->post("payments/{$payment->gateway_payment_id}/refund", [
                'amount' => $amount,
                'speed' => 'normal',
                'notes' => ['payment_id' => (string) $payment->id],
            ]));

        return (string) $refund['id'];
    }

    public function webhook(Request $request): ?WebhookEvent
    {
        $secret = (string) config('payments.gateways.razorpay.webhook_secret');

        if ($secret === '') {
            throw new InvalidWebhook('No Razorpay webhook secret is set.');
        }

        $payload = $request->getContent();

        if (! hash_equals(hash_hmac('sha256', $payload, $secret), (string) $request->header('X-Razorpay-Signature'))) {
            throw new InvalidWebhook('Razorpay webhook signature does not match.');
        }

        /** @var array{event?: string, payload?: array{payment?: array{entity?: array{order_id?: string}}, order?: array{entity?: array{id?: string}}}} $event */
        $event = (array) json_decode($payload, true);
        $type = (string) ($event['event'] ?? '');

        if (! in_array($type, ['payment.authorized', 'payment.captured', 'payment.failed', 'order.paid'], true)) {
            return null;
        }

        $orderId = $event['payload']['payment']['entity']['order_id'] ?? $event['payload']['order']['entity']['id'] ?? null;

        if ($orderId === null) {
            return null;
        }

        $eventId = (string) ($request->header('X-Razorpay-Event-Id') ?: hash('sha256', $payload));

        return new WebhookEvent($eventId, $type, $orderId, ['type' => $type]);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(self::API)
            ->withBasicAuth((string) config('payments.gateways.razorpay.key_id'), (string) config('payments.gateways.razorpay.key_secret'))
            ->asJson()
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(20);
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
            throw new GatewayError('Razorpay could not be reached: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new GatewayError('Razorpay refused: '.($response->json('error.description') ?? 'HTTP '.$response->status()));
        }

        return (array) $response->json();
    }
}
