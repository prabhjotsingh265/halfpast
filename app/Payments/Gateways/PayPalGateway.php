<?php

namespace App\Payments\Gateways;

use App\Models\Booking;
use App\Models\Payment;
use App\Payments\Checkout;
use App\Payments\CheckoutLinks;
use App\Payments\GatewayError;
use App\Payments\GatewayStatus;
use App\Payments\InvalidWebhook;
use App\Payments\PaymentGateway;
use App\Payments\WebhookEvent;
use Illuminate\Http\Request;

/**
 * PayPal, shown as "coming soon". The driver is built once PayPal sandbox
 * keys exist to test it against; until then it can't be switched on, even
 * if keys are added.
 */
class PayPalGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'paypal';
    }

    public function label(): string
    {
        return 'PayPal';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function isTestMode(): bool
    {
        return true;
    }

    public function supports(string $currency): bool
    {
        return in_array(strtoupper($currency), (array) config('payments.gateways.paypal.currencies'), true);
    }

    public function begin(Payment $payment, Booking $booking, CheckoutLinks $links): Checkout
    {
        throw new GatewayError('PayPal is not available yet.');
    }

    public function status(Payment $payment, array $returned = []): GatewayStatus
    {
        throw new GatewayError('PayPal is not available yet.');
    }

    public function capture(Payment $payment): GatewayStatus
    {
        throw new GatewayError('PayPal is not available yet.');
    }

    public function release(Payment $payment): void
    {
        throw new GatewayError('PayPal is not available yet.');
    }

    public function refund(Payment $payment, int $amount, string $idempotencyKey): string
    {
        throw new GatewayError('PayPal is not available yet.');
    }

    public function webhook(Request $request): ?WebhookEvent
    {
        throw new InvalidWebhook('PayPal is not available yet.');
    }
}
