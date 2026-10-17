<?php

namespace App\Http\Controllers\Payments;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Payments\GatewayError;
use App\Payments\Gateways\TestGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The test gateway's own payment page, standing in for Stripe's or PayPal's.
 * Only exists outside production.
 */
class TestGatewayController extends Controller
{
    public function __construct(private TestGateway $gateway)
    {
        abort_unless($gateway->isConfigured(), 404);
    }

    public function show(string $order): Response
    {
        try {
            $state = $this->gateway->load($order);
        } catch (GatewayError) {
            abort(404);
        }

        return Inertia::render('payments/TestCheckout', [
            'order' => $order,
            'amount' => $state['amount'],
            'currency' => $state['currency'],
            'state' => $state['state'],
            'cancelUrl' => $state['cancel'],
        ]);
    }

    public function update(Request $request, string $order): RedirectResponse
    {
        try {
            $state = $this->gateway->load($order);
        } catch (GatewayError) {
            abort(404);
        }

        $action = (string) $request->validate(['action' => ['required', 'in:pay,pay_and_capture,decline,cancel,pay_late,pay_after_taken']])['action'];

        match ($action) {
            'pay' => $this->gateway->pay($order),
            'pay_late' => $this->payLate($order, taken: false),
            'pay_after_taken' => $this->payLate($order, taken: true),
            'pay_and_capture' => $this->gateway->pay($order, capture: true),
            'decline' => $this->gateway->decline($order),
            default => null,
        };

        return redirect()->away($action === 'cancel' ? $state['cancel'] : $state['return']);
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
