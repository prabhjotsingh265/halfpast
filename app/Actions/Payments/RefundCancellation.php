<?php

namespace App\Actions\Payments;

use App\Enums\PaymentOutcome;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Jobs\SendRefund;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Refund;
use App\Payments\GatewayError;
use App\Tenancy\CurrentBusiness;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Gives back what a cancelled booking's `refund_due` says, through the
 * gateway the deposit was paid with. One refund per cancellation, never
 * more than was taken, however many times this runs.
 */
class RefundCancellation
{
    public function __construct(private RefundPayment $refunds, private CurrentBusiness $current) {}

    public function handle(Booking $booking): void
    {
        $booking->refresh();

        if ($booking->refund_due <= 0) {
            return;
        }

        $this->current->set(Business::find($booking->business_id));

        $payments = Payment::withoutGlobalScopes()
            ->where('booking_id', $booking->id)
            ->where('outcome', PaymentOutcome::Confirmed)
            ->latest('id')
            ->get();

        $payment = $payments->first(fn (Payment $p) => in_array($p->status, [PaymentStatus::Captured, PaymentStatus::PartiallyRefunded], true));

        if ($payment === null) {
            // Still being taken: the capture sees the cancellation and lets
            // the money go, or refunds it straight after. Or already let go.
            if ($payments->contains(fn (Payment $p) => in_array($p->status, [PaymentStatus::Authorized, PaymentStatus::Released], true))) {
                return;
            }

            report(new RuntimeException("Booking {$booking->id} was cancelled with {$booking->refund_due} to refund, but has no captured payment."));

            return;
        }

        $reason = $booking->cancelled_by === 'business' ? 'business_cancelled' : 'customer_cancelled';

        $refund = DB::transaction(function () use ($payment, $booking, $reason) {
            $locked = Payment::withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->id);

            // Asked twice (an event delivered again): the first refund stands.
            $existing = Refund::withoutGlobalScopes()->where('payment_id', $locked->id)->where('reason', $reason)->first();

            if ($existing !== null) {
                return $existing;
            }

            $alreadyOnTheWay = (int) Refund::withoutGlobalScopes()->where('payment_id', $locked->id)->where('status', RefundStatus::Pending)->sum('amount');
            $amount = min($booking->refund_due, $locked->amount - $locked->refunded_amount - $alreadyOnTheWay);

            if ($amount <= 0) {
                return null;
            }

            $refund = new Refund(['payment_id' => $locked->id, 'amount' => $amount, 'status' => RefundStatus::Pending, 'reason' => $reason]);
            $refund->business_id = $locked->business_id;
            $refund->save();

            return $refund;
        });

        if ($refund === null || $refund->status !== RefundStatus::Pending) {
            return;
        }

        try {
            $this->refunds->handle($refund);
        } catch (GatewayError $e) {
            report($e);
            SendRefund::dispatch($refund->id)->delay(now()->addMinute());
        }
    }
}
