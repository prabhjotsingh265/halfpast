<?php

namespace App\Http\Controllers\Payments;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SendTestWebhook;
use App\Models\Booking;
use App\Models\Payment;
use App\Payments\GatewayError;
use App\Payments\Gateways\TestGateway;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The test gateway's own payment page, standing in for Stripe's or PayPal's.
 * Exists outside production, and wherever demo mode is on.
 */
class TestGatewayController extends Controller
{
    public function __construct(private TestGateway $gateway)
    {
        // Also open while demo mode is on: demo businesses take test payments
        // even in production, and only they can start one there.
        abort_unless($gateway->isConfigured() || config('demo.enabled'), 404);
    }

    public function show(string $order): Response
    {
        try {
            $this->gateway->load($order);
        } catch (GatewayError) {
            abort(404);
        }

        return Inertia::render('payments/TestCheckout', [...$this->props($order), 'closed' => false]);
    }

    public function update(Request $request, string $order): SymfonyResponse
    {
        try {
            $state = $this->gateway->load($order);
        } catch (GatewayError) {
            abort(404);
        }

        $action = (string) $request->validate(['action' => ['required', 'in:pay,pay_and_capture,pay_and_close,decline,cancel,pay_late,pay_after_taken']])['action'];

        match ($action) {
            'pay', 'pay_and_close' => $this->gateway->pay($order),
            'pay_late' => $this->payLate($order, taken: false),
            'pay_after_taken' => $this->payLate($order, taken: true),
            'pay_and_capture' => $this->gateway->pay($order, capture: true),
            'decline' => $this->gateway->decline($order),
            default => null,
        };

        // Like a real gateway, it also sends a webhook a moment later.
        if ($action !== 'cancel') {
            SendTestWebhook::dispatch($order, $action === 'decline' ? 'payment.failed' : 'payment.authorized')
                ->delay(now()->addSeconds(2));
        }

        // A customer who closes the tab after paying never comes back; only
        // the webhook can settle their booking.
        if ($action === 'pay_and_close') {
            return Inertia::render('payments/TestCheckout', [...$this->props($order), 'closed' => true])->toResponse($request);
        }

        return redirect()->away($action === 'cancel' ? $state['cancel'] : $state['return']);
    }

    /**
     * @return array<string, mixed>
     */
    private function props(string $order): array
    {
        $state = $this->gateway->load($order);

        return [
            'order' => $order,
            'amount' => $state['amount'],
            'currency' => $state['currency'],
            'state' => $state['state'],
        ];
    }

    /**
     * For trying out the late-payment cases without waiting: the hold runs
     * out and is cleared (and, if asked, someone else books the same time)
     * before the customer pays.
     */
    private function payLate(string $order, bool $taken): void
    {
        $payment = Payment::withoutGlobalScopes()->where('gateway', 'test')->where('gateway_order_id', $order)->firstOrFail();
        $booking = Booking::withoutGlobalScopes()->findOrFail($payment->booking_id);

        $booking->update(['status' => BookingStatus::Expired, 'hold_expires_at' => now()->subMinute()]);

        if ($taken) {
            $other = new Booking([
                'service_id' => $booking->service_id,
                'staff_id' => $booking->staff_id,
                'service_name' => $booking->service_name,
                'starts_at' => $booking->starts_at,
                'ends_at' => $booking->ends_at,
                'blocked_until' => $booking->blocked_until,
                'status' => BookingStatus::Confirmed,
                'source' => 'manual',
                'price' => $booking->price,
                'deposit' => 0,
                'currency' => $booking->currency,
                'notes' => 'Booked by someone else while the test payment was open.',
            ]);
            $other->business_id = $booking->business_id;
            $other->save();
        }

        $this->gateway->pay($order);
    }
}
