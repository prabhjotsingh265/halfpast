<?php

namespace App\Jobs;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\GatewayError;
use App\Payments\Gateways;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Someone new has taken a time whose earlier holds had lapsed. Those
 * customers' unfinished checkouts are stopped at the gateway, so they can't
 * go on to pay for a time that's gone. (If one gets paid anyway, settling
 * releases it and nobody is charged.)
 */
class StopUnfinishedCheckouts implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $bookingIds
     */
    public function __construct(public array $bookingIds) {}

    public function handle(Gateways $gateways): void
    {
        Payment::withoutGlobalScopes()
            ->whereIn('booking_id', $this->bookingIds)
            ->where('status', PaymentStatus::Pending)
            ->get()
            ->each(function (Payment $payment) use ($gateways) {
                try {
                    $gateways->get($payment->gateway)->release($payment);
                } catch (GatewayError $e) {
                    report($e);
                }
            });
    }
}
