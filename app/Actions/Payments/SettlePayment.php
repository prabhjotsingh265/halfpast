<?php

namespace App\Actions\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentOutcome;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Events\BookingConfirmed;
use App\Jobs\FinishPayment;
use App\Jobs\SendRefund;
use App\Mail\PaymentReturnedMail;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Refund;
use App\Payments\GatewayError;
use App\Payments\Gateways;
use App\Payments\GatewayState;
use App\Payments\GatewayStatus;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Settles a payment from what its gateway says. The customer coming back
 * from the gateway and the gateway's webhook both call this, in either
 * order, possibly at the same moment and possibly more than once, so it is
 * written to be safe to run any number of times.
 *
 * It works in two halves:
 *
 *  1. Decide, under row locks. Once money is there (authorised, or already
 *     taken), the booking is confirmed if it's still the customer's. A
 *     booking whose hold lapsed is brought back if nobody took the time;
 *     the database's overlap rule decides that. The outcome is saved before
 *     anything else happens.
 *
 *  2. Act on the gateway, outside any transaction: take the money for a
 *     confirmed booking, otherwise release the authorisation (or refund, if
 *     the money was already taken). If the gateway is down, a job retries
 *     from the saved outcome.
 */
class SettlePayment
{
    public function __construct(private Gateways $gateways, private RefundPayment $refunds, private RefundCancellation $refundCancellation) {}

    /**
     * @throws GatewayError When the gateway can't be asked how things stand. Try again later.
     */
    public function handle(Payment $payment, ?GatewayStatus $status = null): Payment
    {
        $status ??= $this->gateways->get($payment->gateway)->status($payment);

        $payment = DB::transaction(fn () => $this->decide($payment->id, $status));

        if ($payment->outcome === null || $payment->status->isSettled()) {
            return $payment;
        }

        return $this->finishOrRetryLater($payment);
    }

    /**
     * The second half on its own, carrying on from a saved outcome.
     *
     * @throws GatewayError
     */
    public function finish(Payment $payment): Payment
    {
        $payment->refresh();

        if ($payment->outcome === null || $payment->status->isSettled()) {
            return $payment;
        }

        return $payment->outcome->keepsMoney() ? $this->takeMoney($payment) : $this->giveBack($payment);
    }

    private function finishOrRetryLater(Payment $payment): Payment
    {
        try {
            return $this->finish($payment);
        } catch (GatewayError $e) {
            report($e);
            FinishPayment::dispatch($payment->id)->delay(now()->addSeconds(30));

            return $payment->refresh();
        }
    }

    private function decide(int $paymentId, GatewayStatus $status): Payment
    {
        $payment = Payment::withoutGlobalScopes()->lockForUpdate()->findOrFail($paymentId);

        // Already decided (a retry, or the webhook after the return) or done.
        if ($payment->outcome !== null || ($payment->status->isSettled() && $payment->status !== PaymentStatus::Failed)) {
            return $payment;
        }

        if (! $status->hasMoney()) {
            if ($status->state === GatewayState::Failed && $payment->status === PaymentStatus::Pending) {
                $payment->update(['status' => PaymentStatus::Failed, 'failure_reason' => $status->reason ?? 'declined']);
            }

            return $payment;
        }

        $outcome = $this->outcomeFor($payment, $status);

        $payment->update([
            'status' => PaymentStatus::Authorized,
            'outcome' => $outcome,
            'gateway_payment_id' => $status->paymentId ?? $payment->gateway_payment_id,
            'authorized_at' => $payment->authorized_at ?? now(),
            'captured_at' => $status->state === GatewayState::Captured ? ($payment->captured_at ?? now()) : $payment->captured_at,
            'failure_reason' => null,
        ]);

        return $payment;
    }

    private function outcomeFor(Payment $payment, GatewayStatus $status): PaymentOutcome
    {
        if ($status->amount !== $payment->amount || ($status->currency !== null && strtoupper($status->currency) !== $payment->currency)) {
            report(new RuntimeException("Payment {$payment->id}: the gateway reported {$status->amount} {$status->currency}, but {$payment->amount} {$payment->currency} was asked for."));

            return PaymentOutcome::AmountMismatch;
        }

        // Replaced by a later attempt, but paid anyway.
        if ($payment->status === PaymentStatus::Failed) {
            return PaymentOutcome::Duplicate;
        }

        $booking = Booking::withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->booking_id);

        return match ($booking->status) {
            // Nobody else can have the time: taking it would have marked this booking expired.
            BookingStatus::Held => $this->confirm($booking),
            BookingStatus::Expired => $this->bringBack($booking),
            BookingStatus::Confirmed => PaymentOutcome::Duplicate,
            default => PaymentOutcome::NotBookable,
        };
    }

    private function confirm(Booking $booking): PaymentOutcome
    {
        Booking::withoutGlobalScopes()->whereKey($booking->id)
            ->update(['status' => BookingStatus::Confirmed, 'hold_expires_at' => null]);

        return PaymentOutcome::Confirmed;
    }

    /**
     * The hold lapsed and was cleared. If the time is still free the booking
     * comes back; if someone else has it, the overlap rule refuses and the
     * money goes back.
     */
    private function bringBack(Booking $booking): PaymentOutcome
    {
        try {
            DB::transaction(function () use ($booking) {
                if ($booking->staff_id !== null) {
                    Booking::lockDiary($booking->staff_id);
                }

                Booking::withoutGlobalScopes()->whereKey($booking->id)
                    ->update(['status' => BookingStatus::Confirmed, 'hold_expires_at' => null]);
            }, Booking::OVERLAP_ATTEMPTS);
        } catch (QueryException $e) {
            if ($e->getCode() !== Booking::OVERLAP_ERROR) {
                throw $e;
            }

            return PaymentOutcome::SlotTaken;
        }

        return PaymentOutcome::Confirmed;
    }

    /**
     * @throws GatewayError
     */
    private function takeMoney(Payment $payment): Payment
    {
        $booking = Booking::withoutGlobalScopes()->findOrFail($payment->booking_id);

        // Cancelled before the money was taken, with all of it due back:
        // let the authorisation go rather than take the money and refund it.
        if ($payment->captured_at === null && $booking->status === BookingStatus::Cancelled && $booking->refund_due >= $payment->amount) {
            $this->gateways->get($payment->gateway)->release($payment);

            Payment::withoutGlobalScopes()->whereKey($payment->id)
                ->where('status', PaymentStatus::Authorized)
                ->update(['status' => PaymentStatus::Released, 'released_at' => now(), 'release_reason' => 'booking_cancelled']);

            return $payment->refresh();
        }

        $capturedId = $payment->gateway_payment_id;

        if ($payment->captured_at === null) {
            $capturedId = $this->gateways->get($payment->gateway)->capture($payment)->paymentId ?? $capturedId;
        }

        // Whoever moves it to captured records the deposit and sends the
        // confirmation, so neither ever happens twice.
        $won = Payment::withoutGlobalScopes()->whereKey($payment->id)
            ->where('status', PaymentStatus::Authorized)
            ->update([
                'status' => PaymentStatus::Captured,
                'captured_at' => $payment->captured_at ?? now(),
                'gateway_payment_id' => $capturedId,
            ]);

        $payment->refresh();

        if ($won === 1) {
            self::recordDeposit($payment);
            $booking->refresh();

            if ($booking->status === BookingStatus::Cancelled) {
                // Cancelled while the money was on its way. Now it's here,
                // whatever the cancellation said is due back can go back.
                $this->refundCancellation->handle($booking);
            } else {
                BookingConfirmed::dispatch($booking);
            }
        }

        return $payment;
    }

    /**
     * @throws GatewayError
     */
    private function giveBack(Payment $payment): Payment
    {
        $outcome = $payment->outcome;

        if ($payment->captured_at !== null) {
            // The money was already taken, so it comes back as a refund. The
            // payment is marked captured and its refund created together, so
            // a crash can't leave money taken with no refund on the way.
            self::recordDeposit($payment);

            $created = DB::transaction(function () use ($payment, $outcome) {
                $won = Payment::withoutGlobalScopes()->whereKey($payment->id)
                    ->where('status', PaymentStatus::Authorized)
                    ->update(['status' => PaymentStatus::Captured, 'release_reason' => $outcome?->value]);

                if ($won === 1) {
                    $refund = new Refund(['payment_id' => $payment->id, 'amount' => $payment->amount - $payment->refunded_amount, 'status' => RefundStatus::Pending, 'reason' => (string) $outcome?->value]);
                    $refund->business_id = $payment->business_id;
                    $refund->save();
                }

                return $won === 1;
            });

            if ($created) {
                $this->tellCustomer($payment);
            }

            $refund = Refund::withoutGlobalScopes()->where('payment_id', $payment->id)->where('reason', (string) $outcome?->value)->firstOrFail();

            try {
                $this->refunds->handle($refund);
            } catch (GatewayError $e) {
                report($e);
                SendRefund::dispatch($refund->id)->delay(now()->addMinute());
            }

            return $payment->refresh();
        }

        $this->gateways->get($payment->gateway)->release($payment);

        $won = Payment::withoutGlobalScopes()->whereKey($payment->id)
            ->where('status', PaymentStatus::Authorized)
            ->update(['status' => PaymentStatus::Released, 'released_at' => now(), 'release_reason' => $outcome?->value]);

        if ($won === 1) {
            $this->tellCustomer($payment);
        }

        return $payment->refresh();
    }

    private function tellCustomer(Payment $payment): void
    {
        // An amount that didn't match is for us to look into, not a message for the customer.
        if ($payment->outcome === PaymentOutcome::AmountMismatch) {
            return;
        }

        $booking = Booking::withoutGlobalScopes()->findOrFail($payment->booking_id);
        $customer = Customer::withoutGlobalScopes()->find($booking->customer_id);

        if ($customer?->email !== null) {
            Mail::to($customer->email, $customer->name)->send(new PaymentReturnedMail($payment->refresh()));
        }
    }

    public static function recordDeposit(Payment $payment): void
    {
        try {
            DB::transaction(function () use ($payment) {
                $entry = new LedgerEntry;
                $entry->forceFill([
                    'business_id' => $payment->business_id,
                    'payment_id' => $payment->id,
                    'type' => 'deposit',
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'gateway' => $payment->gateway,
                ])->save();
            });
        } catch (UniqueConstraintViolationException) {
            // Already recorded.
        }
    }
}
