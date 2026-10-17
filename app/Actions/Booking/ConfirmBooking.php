<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Events\BookingConfirmed;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;

/**
 * Turns a held booking into a confirmed one, exactly once, however many
 * times it's asked (a double click, or a payment gateway sending the same
 * notification twice).
 */
class ConfirmBooking
{
    /**
     * @return bool Whether this call confirmed it. False if it already was,
     *              or the hold has gone to someone else.
     */
    public function handle(Booking $booking): bool
    {
        $confirmed = DB::transaction(function () use ($booking) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            // A lapsed hold whose status is still "held" hasn't been taken by
            // anyone else (taking it would have marked it expired), so it can
            // still be confirmed.
            if ($locked->status !== BookingStatus::Held) {
                return false;
            }

            $locked->update(['status' => BookingStatus::Confirmed, 'hold_expires_at' => null]);

            return true;
        });

        $booking->refresh();

        if ($confirmed) {
            BookingConfirmed::dispatch($booking);
        }

        return $confirmed;
    }
}
