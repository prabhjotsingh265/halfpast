<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Events\CalendarChanged;

/**
 * A paid booking's deposit is taken after it's confirmed, without the
 * booking itself changing, so its "deposit paid" only shows once calendars
 * hear about it from here.
 */
class RefreshCalendars
{
    public function handle(BookingConfirmed $event): void
    {
        $booking = $event->booking;

        CalendarChanged::between($booking->business_id, $booking->starts_at, $booking->blocked_until);
    }
}
