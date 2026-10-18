<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Jobs\StopUnfinishedCheckouts;
use App\Models\Booking;
use Carbon\CarbonImmutable;

/**
 * Before a new booking takes a person's time: their holds that have run out
 * are marked expired. They still count for the database's overlap rule
 * until then, so this can't wait for the scheduled clean-up. Customers whose
 * lapsed hold is being taken have their unfinished checkouts stopped once
 * the new booking commits.
 *
 * Runs inside the transaction that inserts the new booking.
 */
class ClearLapsedHolds
{
    public function handle(int $businessId, int $staffId, CarbonImmutable $startsAt, CarbonImmutable $blockedUntil, CarbonImmutable $now): void
    {
        $lapsed = Booking::where('business_id', $businessId)
            ->where('staff_id', $staffId)
            ->where('status', BookingStatus::Held)
            ->where('hold_expires_at', '<=', $now);

        $overlapping = (clone $lapsed)
            ->where('starts_at', '<', $blockedUntil)
            ->where('blocked_until', '>', $startsAt)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $lapsed->update(['status' => BookingStatus::Expired]);

        if ($overlapping !== []) {
            StopUnfinishedCheckouts::dispatch(array_values($overlapping))->afterCommit();
        }
    }
}
