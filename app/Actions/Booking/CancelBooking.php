<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Events\BookingCancelled;
use App\Models\Booking;
use App\Models\Business;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a confirmed booking, frees its time, and records how much of the
 * deposit goes back. Happens once, under a row lock.
 */
class CancelBooking
{
    /**
     * @param  'customer'|'business'  $by
     * @param  bool  $refundInFull  The business choosing to refund regardless of the window.
     */
    public function handle(Booking $booking, string $by, bool $refundInFull = false, ?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();

        $cancelled = DB::transaction(function () use ($booking, $by, $refundInFull, $now) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $business = Business::findOrFail($locked->business_id);
            $terms = CancellationTerms::for($locked, $business, $now);

            if (! $terms->allowed) {
                return false;
            }

            $locked->update([
                'status' => BookingStatus::Cancelled,
                'cancelled_at' => $now,
                'cancelled_by' => $by,
                'refund_due' => $refundInFull ? $locked->deposit : $terms->refund,
            ]);

            return true;
        });

        $booking->refresh();

        if ($cancelled) {
            BookingCancelled::dispatch($booking);
        }

        return $cancelled;
    }
}
