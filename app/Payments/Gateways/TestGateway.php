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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * A pretend gateway that behaves like a real one: its own payment page, money
 * authorised first and captured later, refunds, and signed webhooks. It keeps
 * its state in the cache, the way a real gateway keeps its own.
 *
 * Used for local development, the demo, and tests. Never in production.
 */
class TestGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'test';
    }

    public function label(): string
    {
        return 'Test payment';
    }

    public function isConfigured(): bool
    {
        return (bool) config('payments.gateways.test.enabled') && ! app()->isProduction();
    }

    public function isTestMode(): bool
    {
        return true;
    }

    public function supports(string $currency): bool
    {
        return true;
    }

    public function begin(Payment $payment, Booking $booking, CheckoutLinks $links): Checkout
    {
        $this->failIfAsked('begin');

        $order = 'test_order_'.Str::lower(Str::random(20));

        $this->save($order, [
            'state' => 'pending',
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'payment_id' => null,
            'refunded' => 0,
            'refunds' => [],
            'return' => $links->return,
            'cancel' => $links->cancel,
        ]);

        $payment->update(['gateway_order_id' => $order]);

        return new Checkout(redirectUrl: URL::signedRoute('payments.test.show', ['order' => $order]));
    }

    public function status(Payment $payment, array $returned = []): GatewayStatus
    {
        return $this->statusOf($this->load((string) $payment->gateway_order_id));
    }

    public function capture(Payment $payment): GatewayStatus
    {
        $this->failIfAsked('capture');
        $order = (string) $payment->gateway_order_id;
        $state = $this->load($order);

        if ($state['state'] === 'authorized') {
            $state['state'] = 'captured';
            $this->save($order, $state);
        } elseif ($state['state'] !== 'captured') {
            throw new GatewayError("Can't capture a payment that is {$state['state']}.");
        }

        return $this->statusOf($state);
    }

    public function release(Payment $payment): void
    {
        $this->failIfAsked('release');
        $order = (string) $payment->gateway_order_id;
        $state = $this->load($order);

        if (in_array($state['state'], ['pending', 'authorized'], true)) {
            $state['state'] = $state['state'] === 'pending' ? 'cancelled' : 'released';
            $this->save($order, $state);
        }
    }

    public function refund(Payment $payment, int $amount, string $idempotencyKey): string
    {
        $this->failIfAsked('refund');
        $order = (string) $payment->gateway_order_id;
        $state = $this->load($order);

        // Retrying the same refund returns the first one, like Stripe's idempotency keys.
        if (isset($state['refunds'][$idempotencyKey])) {
            return $state['refunds'][$idempotencyKey];
        }

        if ($state['state'] !== 'captured' || $state['amount'] < $state['refunded'] + $amount) {
            throw new GatewayError('Refund refused: more than what was captured.');
        }

        $refundId = 'test_refund_'.Str::lower(Str::random(16));
        $state['refunded'] += $amount;
        $state['refunds'][$idempotencyKey] = $refundId;
        $this->save($order, $state);

        return $refundId;
    }

    public function webhook(Request $request): ?WebhookEvent
    {
        $expected = hash_hmac('sha256', $request->getContent(), self::secret());

        if (! hash_equals($expected, (string) $request->header('X-Test-Signature'))) {
            throw new InvalidWebhook('Bad signature.');
        }

        /** @var array{id?: string, type?: string, order?: string} $event */
        $event = (array) $request->json()->all();

        if (! isset($event['id'], $event['type'], $event['order'])) {
            return null;
        }

        return new WebhookEvent($event['id'], $event['type'], $event['order'], $event);
    }

    /*
    | What the test payment page (and tests) can do, standing in for the
    | customer at the gateway.
    */

    /**
     * The customer pays. Most methods only authorise; some take the money
     * straight away, which `capture` stands for.
     */
    public function pay(string $order, bool $capture = false): void
    {
        $state = $this->load($order);

        if ($state['state'] === 'pending') {
            $state['state'] = $capture ? 'captured' : 'authorized';
            $state['payment_id'] = 'test_pay_'.Str::lower(Str::random(16));
            $this->save($order, $state);
        }
    }

    public function decline(string $order): void
    {
        $state = $this->load($order);

        if ($state['state'] === 'pending') {
            $state['state'] = 'declined';
            $this->save($order, $state);
        }
    }

    /**
     * @return array{state: string, amount: int, currency: string, payment_id: ?string, refunded: int, refunds: array<string, string>, return: string, cancel: string}
     */
    public function load(string $order): array
    {
        $state = Cache::get(self::cacheKey($order));

        if (! is_array($state)) {
            throw new GatewayError("The test gateway has no order {$order}.");
        }

        /** @var array{state: string, amount: int, currency: string, payment_id: ?string, refunded: int, refunds: array<string, string>, return: string, cancel: string} $state */
        return $state;
    }

    /**
     * The JSON body and signature a webhook for this order would carry.
     *
     * @return array{string, string}
     */
    public function signedEvent(string $order, string $type): array
    {
        $body = (string) json_encode(['id' => 'test_evt_'.Str::lower(Str::random(16)), 'type' => $type, 'order' => $order]);

        return [$body, hash_hmac('sha256', $body, self::secret())];
    }

    /** Make the next call to an operation fail, as if the gateway were down. */
    public static function failNext(string $operation): void
    {
        Cache::put('payments.test.fail.'.$operation, true, now()->addHour());
    }

    private function failIfAsked(string $operation): void
    {
        if (Cache::pull('payments.test.fail.'.$operation)) {
            throw new GatewayError("The test gateway was asked to fail the next {$operation}.");
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function save(string $order, array $state): void
    {
        // Kept as long as a real gateway would remember an order.
        Cache::put(self::cacheKey($order), $state, now()->addDays(30));
    }

    /**
     * @param  array{state: string, amount: int, currency: string, payment_id: ?string}  $state
     */
    private function statusOf(array $state): GatewayStatus
    {
        return new GatewayStatus(
            state: match ($state['state']) {
                'authorized' => GatewayState::Authorized,
                'captured' => GatewayState::Captured,
                'pending' => GatewayState::Pending,
                default => GatewayState::Failed,
            },
            paymentId: $state['payment_id'],
            amount: $state['amount'],
            currency: $state['currency'],
            reason: in_array($state['state'], ['declined', 'cancelled', 'released'], true) ? $state['state'] : null,
        );
    }

    private static function cacheKey(string $order): string
    {
        return 'payments.test.order.'.$order;
    }

    private static function secret(): string
    {
        return hash('sha256', 'halfpast-test-gateway|'.config('app.key'));
    }
}
