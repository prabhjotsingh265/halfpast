<?php

namespace App\Availability;

/**
 * Everything the slot finder needs to know about one person: when they
 * usually work, when they're off, and what's already booked with them.
 */
final readonly class StaffCalendar
{
    public function __construct(
        public int $staffId,
        public WeeklySchedule $week,
        public IntervalSet $timeOff = new IntervalSet,
        /** Bookings and holds, each including the free time after it. */
        public IntervalSet $busy = new IntervalSet,
    ) {}
}
