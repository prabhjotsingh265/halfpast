<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Marks holds that ran out as expired, across every business, so their
 * times show as free again. Runs every minute. Holding a time also clears
 * that person's lapsed holds on the spot, so nobody waits for this.
 */
#[Signature('bookings:expire-holds')]
#[Description('Release held times whose hold has run out')]
class ExpireHolds extends Command
{
    public function handle(): int
    {
        $count = Booking::withoutGlobalScopes()
            ->where('status', BookingStatus::Held)
            ->where('hold_expires_at', '<=', now())
            ->update(['status' => BookingStatus::Expired, 'updated_at' => now()]);

        $this->info("Released {$count} lapsed ".str('hold')->plural($count).'.');

        return self::SUCCESS;
    }
}
