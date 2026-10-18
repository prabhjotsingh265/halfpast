<?php

namespace App\Observers;

use App\Enums\BookingStatus;
use App\Events\CalendarChanged;
use App\Models\Booking;
use App\Models\BookingEvent;
use Carbon\CarbonImmutable;

/**
 * Keeps a booking's history (made, confirmed, marked, cancelled, and by
 * whom), and tells open calendars when it appears, moves, or changes in a
 * way they show: its status, times, person, customer or notes.
 */
class BookingObserver
{
    private const SHOWN = ['status', 'starts_at', 'ends_at', 'blocked_until', 'staff_id', 'customer_id', 'notes', 'hold_expires_at'];

    public function created(Booking $booking): void
    {
        BookingEvent::record($booking, 'created', ['source' => $booking->source, 'status' => $booking->status->value]);

        CalendarChanged::between($booking->business_id, $booking->starts_at, $booking->blocked_until);
    }

    // Not saved() with wasRecentlyCreated: that stays true for the model's
    // whole life, so every later save would look like a new booking.
    public function updated(Booking $booking): void
    {
        if ($booking->wasChanged('status')) {
            BookingEvent::record($booking, $booking->status->value, $booking->status === BookingStatus::Cancelled
                ? ['by' => $booking->cancelled_by, 'refund_due' => $booking->refund_due]
                : []);
        }

        if (! $booking->wasChanged(self::SHOWN)) {
            return;
        }

        // A booking that moved changes the day it left as well as the one it went to.
        $starts = array_filter([$booking->starts_at, self::moment($booking->getOriginal('starts_at'))]);
        $ends = array_filter([$booking->blocked_until, self::moment($booking->getOriginal('blocked_until'))]);

        CalendarChanged::between($booking->business_id, min($starts), max($ends));
    }

    public function deleted(Booking $booking): void
    {
        CalendarChanged::between($booking->business_id, $booking->starts_at, $booking->blocked_until);
    }

    private static function moment(mixed $value): ?CarbonImmutable
    {
        return $value instanceof \DateTimeInterface ? CarbonImmutable::instance($value) : null;
    }
}
