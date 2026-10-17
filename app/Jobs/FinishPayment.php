<?php

namespace App\Jobs;

use App\Actions\Payments\SettlePayment;
use App\Enums\BookingStatus;
use App\Enums\PaymentOutcome;
use App\Enums\PaymentStatus;
use App\Mail\PaymentReturnedMail;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Payment;
use App\Payments\GatewayError;
use App\Payments\Gateways;
use App\Tenancy\CurrentBusiness;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Carries on settling a payment whose gateway call failed: taking the money
 * for a confirmed booking, or giving it back. Retried with growing gaps.
 */
class FinishPayment implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public function __construct(public int $paymentId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 600, 1800, 3600];
    }

    public function handle(SettlePayment $settle, CurrentBusiness $current): void
    {
        $payment = Payment::withoutGlobalScopes()->find($this->paymentId);

        if ($payment === null) {
            return;
        }

        $current->set(Business::find($payment->business_id));
        $settle->finish($payment);
    }

    /**
     * Every retry failed. If the money for a confirmed booking couldn't be
     * taken, the booking doesn't stand: it's cancelled, the authorisation let
     * go, and the customer told. A slot is never kept for money not taken.
     */
    public function failed(?Throwable $exception): void
    {
        $payment = Payment::withoutGlobalScopes()->find($this->paymentId);

        if ($payment === null || $payment->outcome !== PaymentOutcome::Confirmed || $payment->status !== PaymentStatus::Authorized || $payment->captured_at !== null) {
            return;
        }

        Booking::withoutGlobalScopes()->whereKey($payment->booking_id)
            ->where('status', BookingStatus::Confirmed)
            ->update(['status' => BookingStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_by' => 'system', 'refund_due' => 0]);

        $payment->update(['outcome' => PaymentOutcome::CaptureFailed]);

        try {
            app(Gateways::class)->get($payment->gateway)->release($payment);
        } catch (GatewayError $e) {
            // An authorisation nobody captures lapses on its own at the gateway.
            report($e);
        }

        $payment->update(['status' => PaymentStatus::Released, 'released_at' => now(), 'release_reason' => PaymentOutcome::CaptureFailed->value]);

        $booking = Booking::withoutGlobalScopes()->findOrFail($payment->booking_id);
        $customer = Customer::withoutGlobalScopes()->find($booking->customer_id);

        if ($customer?->email !== null) {
            Mail::to($customer->email, $customer->name)->send(new PaymentReturnedMail($payment->refresh()));
        }
    }
}
