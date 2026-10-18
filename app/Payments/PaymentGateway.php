<?php

namespace App\Payments;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * What every payment gateway can do. The flows differ (Stripe and PayPal
 * send the customer to their own page and back; Razorpay opens a pop-up on
 * ours), but each one authorises money first and lets us take it, release
 * it or refund it afterwards.
 */
interface PaymentGateway
{
    /** "stripe", "paypal", "razorpay" or "test". */
    public function key(): string;

    /** What the customer sees on the button: "Card", "PayPal", "UPI, cards and more". */
    public function label(): string;

    /** Whether its keys are set, so it can be offered at all. */
    public function isConfigured(): bool;

    /** Whether it's using test keys, so no real money moves. */
    public function isTestMode(): bool;

    public function supports(string $currency): bool;

    /**
     * Start paying at the gateway. Sets the payment's gateway order id.
     *
     * @throws GatewayError
     */
    public function begin(Payment $payment, Booking $booking, CheckoutLinks $links): Checkout;

    /**
     * Where the payment stands, asked of the gateway itself. Never taken from
     * what the browser says.
     *
     * @param  array<string, mixed>  $returned  What came back with the customer, if anything.
     *
     * @throws GatewayError
     */
    public function status(Payment $payment, array $returned = []): GatewayStatus;

    /**
     * Take the authorised money.
     *
     * @throws GatewayError
     */
    public function capture(Payment $payment): GatewayStatus;

    /**
     * Let an authorisation go (or stop an unfinished checkout) so the customer
     * is never charged.
     *
     * @throws GatewayError
     */
    public function release(Payment $payment): void;

    /**
     * Give money back. The key makes a retried refund count only once.
     *
     * @return string The gateway's refund id.
     *
     * @throws GatewayError
     */
    public function refund(Payment $payment, int $amount, string $idempotencyKey): string;

    /**
     * Read a webhook after checking its signature. Null for events this app
     * doesn't act on.
     *
     * @throws InvalidWebhook
     */
    public function webhook(Request $request): ?WebhookEvent;
}
