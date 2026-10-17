<?php

namespace App\Actions\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentNotStarted;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Payment;
use App\Payments\Checkout;
use App\Payments\CheckoutLinks;
use App\Payments\GatewayError;
use App\Payments\Gateways;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sends the customer off to pay their deposit.
 *
 * Starting a payment gives the hold a fresh payment window (15 minutes), so
 * the clock can't run out while the customer is on the gateway's page. A
 * hold never lasts longer than the maximum (45 minutes) in total, however
 * often "Pay" is pressed. An earlier unfinished attempt is replaced; if it
 * gets paid anyway, settling treats it as a duplicate and gives it back.
 */
class StartPayment
{
    public function __construct(private Gateways $gateways) {}

    /**
     * @return array{Payment, Checkout}
     *
     * @throws PaymentNotStarted
     */
    public function handle(Business $business, Booking $booking, string $gatewayKey, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $gateway = $this->gateways->for($business)[$gatewayKey] ?? throw PaymentNotStarted::unavailable();

        if ($booking->deposit <= 0 || $booking->customer_id === null) {
            throw PaymentNotStarted::notReady();
        }

        /** @var Collection<int, Payment> $replaced */
        $replaced = new Collection;

        $payment = DB::transaction(function () use ($booking, $gatewayKey, $now, &$replaced) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== BookingStatus::Held || $locked->created_at === null || $locked->hold_expires_at === null) {
                throw PaymentNotStarted::holdGone();
            }

            $latest = $locked->created_at->addMinutes((int) config('payments.max_hold_minutes'));
            $wanted = $now->addMinutes((int) config('payments.payment_window_minutes'));
            $until = $locked->hold_expires_at->max($wanted)->min($latest);

            if ($until->lessThanOrEqualTo($now)) {
                throw PaymentNotStarted::heldTooLong();
            }

            $locked->update(['hold_expires_at' => $until]);

            $replaced = Payment::where('booking_id', $locked->id)->where('status', PaymentStatus::Pending)->get();
            Payment::whereKey($replaced->modelKeys())->update(['status' => PaymentStatus::Failed, 'failure_reason' => 'replaced']);

            $payment = new Payment([
                'booking_id' => $locked->id,
                'gateway' => $gatewayKey,
                'amount' => $locked->deposit,
                'currency' => $locked->currency,
                'status' => PaymentStatus::Pending,
            ]);
            $payment->business_id = $locked->business_id;
            $payment->save();

            return $payment;
        });

        // Gateways are only called once the locks are let go.
        $this->stopAtGateway($replaced);

        try {
            $checkout = $gateway->begin($payment, $booking, new CheckoutLinks(
                return: route('booking.payment.return', ['business' => $business, 'booking' => $booking, 'payment' => $payment->id]),
                cancel: route('booking.checkout', ['business' => $business, 'booking' => $booking, 'cancelled' => 1]),
            ));
        } catch (GatewayError $e) {
            report($e);
            $payment->update(['status' => PaymentStatus::Failed, 'failure_reason' => 'could_not_start']);

            throw PaymentNotStarted::gatewayDown();
        }

        return [$payment->refresh(), $checkout];
    }

    /**
     * Marks a booking's unfinished payments as replaced, for when its time is
     * held again under a new booking. If one gets paid anyway, settling
     * treats it as a duplicate and gives it back.
     */
    public function replaceUnfinished(Booking $booking): void
    {
        $unfinished = Payment::withoutGlobalScopes()
            ->where('booking_id', $booking->id)
            ->where('status', PaymentStatus::Pending)
            ->get();

        Payment::withoutGlobalScopes()->whereKey($unfinished->modelKeys())
            ->update(['status' => PaymentStatus::Failed, 'failure_reason' => 'replaced']);

        $this->stopAtGateway($unfinished);
    }

    /**
     * @param  Collection<int, Payment>  $payments
     */
    private function stopAtGateway(Collection $payments): void
    {
        foreach ($payments as $payment) {
            try {
                $this->gateways->get($payment->gateway)->release($payment);
            } catch (GatewayError $e) {
                // If it's paid anyway, settling gives it back.
                report($e);
            }
        }
    }
}
