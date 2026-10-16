<?php

namespace App\Availability;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Works out the times a service can be booked. It knows nothing about the
 * database: it's given each person's week, time off and bookings, and the
 * business's closures, and returns the free slots in order.
 *
 * Weekly hours are local clock times, so each day's hours are turned into
 * real moments for that particular date. 09:00 in London is 08:00 UTC in
 * summer and 09:00 UTC in winter, and a day the clocks change on is simply
 * 23 or 25 hours long. From there everything is plain arithmetic on Unix
 * seconds.
 *
 * A slot needs:
 *  - the whole appointment inside someone's working hours, outside their
 *    time off and any closure;
 *  - the appointment plus the free time after it clear of their bookings
 *    (the free time may run past the end of the day, since nobody else is
 *    booked then);
 *  - a start between the request's earliest and latest moments.
 *
 * Slots start every `step` minutes from the beginning of each stretch of
 * working hours, so someone who starts at 09:10 offers 09:10, 09:25, ...
 */
final class SlotFinder
{
    /**
     * @param  iterable<StaffCalendar>  $staff
     * @return list<Slot>
     */
    public function find(SlotRequest $request, iterable $staff, IntervalSet $closures = new IntervalSet): array
    {
        $duration = $request->durationMinutes * 60;
        $occupied = ($request->durationMinutes + $request->bufferMinutes) * 60;
        $step = $request->stepMinutes * 60;
        $earliest = $request->earliest->getTimestamp();
        $latest = $request->latest->getTimestamp();

        /** @var array<int, list<int>> $staffByStart */
        $staffByStart = [];

        foreach ($staff as $calendar) {
            foreach ($this->dates($request->from, $request->to) as $date) {
                $hours = new IntervalSet($this->hoursOn($date, $calendar->week, $request->zone));
                $working = $hours->subtract($calendar->timeOff)->subtract($closures);

                if ($working->isEmpty()) {
                    continue;
                }

                foreach ($hours->intervals as $stretch) {
                    for ($start = $stretch->start; $start + $duration <= $stretch->end; $start += $step) {
                        if ($start < $earliest || $start > $latest) {
                            continue;
                        }

                        if (! $working->covers(new Interval($start, $start + $duration))) {
                            continue;
                        }

                        if ($calendar->busy->overlaps(new Interval($start, $start + $occupied))) {
                            continue;
                        }

                        $staffByStart[$start][] = $calendar->staffId;
                    }
                }
            }
        }

        ksort($staffByStart);

        $slots = [];

        foreach ($staffByStart as $start => $staffIds) {
            $staffIds = array_values(array_unique($staffIds));
            sort($staffIds);

            $slots[] = new Slot(
                (new DateTimeImmutable)->setTimestamp($start),
                (new DateTimeImmutable)->setTimestamp($start + $duration),
                $staffIds,
            );
        }

        return $slots;
    }

    /**
     * Someone's working hours on one local date, as real moments.
     *
     * @return list<Interval>
     */
    public function hoursOn(string $date, WeeklySchedule $week, DateTimeZone $zone): array
    {
        $weekday = (int) (new DateTimeImmutable($date, $zone))->format('N');
        $hours = [];

        foreach ($week->on($weekday) as [$start, $end]) {
            $from = self::localTime($date, $start, $zone);
            $until = self::localTime($date, $end, $zone);

            if ($until > $from) {
                $hours[] = Interval::between($from, $until);
            }
        }

        return $hours;
    }

    /**
     * The moment a local clock time happens on a date. A time the clocks skip
     * over (01:30 when they jump from 01:00 to 02:00) moves on by the size of
     * the jump, which is how PHP reads it.
     */
    public static function localTime(string $date, int $minutes, DateTimeZone $zone): DateTimeImmutable
    {
        $midnight = new DateTimeImmutable($date, $zone);

        if ($minutes >= 1440) {
            return $midnight->modify('+1 day')->setTime(0, 0);
        }

        return $midnight->setTime(intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * Each date from one to another, both included.
     *
     * @return iterable<string>
     */
    private function dates(string $from, string $to): iterable
    {
        $utc = new DateTimeZone('UTC');
        $date = new DateTimeImmutable($from, $utc);

        while (($day = $date->format('Y-m-d')) <= $to) {
            yield $day;
            $date = $date->modify('+1 day');
        }
    }
}
