<?php

namespace App\Listeners;

use App\Actions\Payments\RefundCancellation;
use App\Events\BookingCancelled;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * Gives the deposit back when a paid booking is cancelled in time (or by
 * the business), through the gateway it was paid with.
 */
class RefundCancelledDeposit implements ShouldQueueAfterCommit
{
    public function __construct(private RefundCancellation $refund) {}

    public function handle(BookingCancelled $event): void
    {
        $this->refund->handle($event->booking);
    }
}
