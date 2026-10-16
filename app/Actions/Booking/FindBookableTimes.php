<?php

namespace App\Actions\Booking;

use App\Availability\Interval;
use App\Availability\IntervalSet;
use App\Availability\Slot;
use App\Availability\SlotFinder;
use App\Availability\SlotRequest;
use App\Availability\StaffCalendar;
use App\Availability\WeeklySchedule;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Service;
use App\Models\Staff;
use App\Models\TimeOff;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Loads what the slot finder needs from the database (hours, time off,
 * closures and bookings for the people who do a service) and asks it for
 * the free times between two local dates.
 */
class FindBookableTimes
{
    public function __construct(private SlotFinder $finder) {}

    /**
     * @param  string  $from  First local date, "2026-11-02".
     * @param  string  $to  Last local date, included.
     * @return list<Slot>
     */
    public function handle(Business $business, Service $service, ?Staff $only, string $from, string $to, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $staff = $this->staffFor($business, $service, $only);

        if ($staff->isEmpty()) {
            return [];
        }

        $zone = $business->timezone;
        // A day either side, since local dates and UTC dates don't line up.
        $windowStart = CarbonImmutable::parse($from, $zone)->subDay()->utc();
        $windowEnd = CarbonImmutable::parse($to, $zone)->addDays(2)->utc();
        $staffIds = $staff->pluck('id')->all();

        $hours = WorkingHour::where('business_id', $business->id)
            ->whereIn('staff_id', $staffIds)
            ->get()
            ->groupBy('staff_id');

        $timeOff = TimeOff::where('business_id', $business->id)
            ->where('starts_at', '<', $windowEnd)
            ->where('ends_at', '>', $windowStart)
            ->where(fn ($query) => $query->whereNull('staff_id')->orWhereIn('staff_id', $staffIds))
            ->get();

        $bookings = Booking::where('business_id', $business->id)
            ->whereIn('staff_id', $staffIds)
            ->takingTime($now)
            ->where('starts_at', '<', $windowEnd)
            ->where('blocked_until', '>', $windowStart)
            ->get()
            ->groupBy('staff_id');

        $calendars = $staff->map(fn (Staff $member) => new StaffCalendar(
            $member->id,
            WeeklySchedule::fromRows($hours->get($member->id, collect())->map(fn (WorkingHour $hour) => [
                'weekday' => $hour->weekday,
                'starts_at' => $hour->starts_at,
                'ends_at' => $hour->ends_at,
            ])),
            self::intervals($timeOff->where('staff_id', $member->id), 'ends_at'),
            self::intervals($bookings->get($member->id, collect()), 'blocked_until'),
        ));

        $request = new SlotRequest(
            $zone,
            $from,
            $to,
            $service->duration_minutes,
            $service->buffer_after_minutes,
            $business->slot_step_minutes,
            $now->addMinutes($business->min_notice_minutes),
            $now->addDays($business->booking_horizon_days),
        );

        return $this->finder->find($request, $calendars, self::intervals($timeOff->whereNull('staff_id'), 'ends_at'));
    }

    /**
     * The people who do this service and are taking bookings.
     *
     * @return Collection<int, Staff>
     */
    public function staffFor(Business $business, Service $service, ?Staff $only = null): Collection
    {
        return $service->staff()
            ->where('staff.business_id', $business->id)
            ->where('staff.is_active', true)
            ->when($only, fn ($query) => $query->whereKey($only->id))
            ->orderBy('staff.position')
            ->orderBy('staff.id')
            ->get();
    }

    /**
     * @param  iterable<TimeOff|Booking>  $rows
     */
    private static function intervals(iterable $rows, string $endColumn): IntervalSet
    {
        $intervals = [];

        foreach ($rows as $row) {
            /** @var CarbonImmutable $end */
            $end = $row->getAttribute($endColumn);
            $intervals[] = Interval::between($row->starts_at, $end);
        }

        return new IntervalSet($intervals);
    }
}
