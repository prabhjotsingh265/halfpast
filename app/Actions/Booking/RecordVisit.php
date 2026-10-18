<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Records how an appointment went: the customer came (completed) or didn't
 * (no-show). Only once it has started, so a booking still to come can't be
 * marked by mistake and lose its time; and it can be changed later if it was
 * marked wrongly. The deposit stays as it is either way.
 */
class RecordVisit
{
    public static function allowed(Booking $booking, ?CarbonImmutable $now = null): bool
    {
        return in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::Completed, BookingStatus::NoShow], true)
            && $booking->starts_at->lessThanOrEqualTo($now ?? CarbonImmutable::now());
    }

    public function handle(Booking $booking, BookingStatus $outcome, ?CarbonImmutable $now = null): bool
    {
        if (! in_array($outcome, [BookingStatus::Completed, BookingStatus::NoShow], true)) {
            throw new InvalidArgumentException("A visit is completed or a no-show, not {$outcome->value}.");
        }

        $recorded = DB::transaction(function () use ($booking, $outcome, $now) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (! self::allowed($locked, $now)) {
                return false;
            }

            $locked->update(['status' => $outcome]);

            return true;
        });

        $booking->refresh();

        return $recorded;
    }
}
