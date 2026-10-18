<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Exceptions\TimeNotAvailable;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Service;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a time for a customer while they finish booking: a booking with the
 * status "held" that lapses after the business's hold time (15 minutes by
 * default).
 *
 * The time is checked against the rules first (hours, notice, horizon), but
 * the real guarantee is the database: two customers holding the same time at
 * the same moment can both pass that check, and PostgreSQL's overlap rule
 * lets exactly one of them in. With "anyone free", the next free person is
 * tried before giving up.
 */
class HoldTime
{
    public function __construct(private FindBookableTimes $times, private ClearLapsedHolds $clearLapsedHolds) {}

    public function handle(Business $business, Service $service, ?Staff $staff, CarbonImmutable $startsAt, ?CarbonImmutable $now = null): Booking
    {
        $now ??= CarbonImmutable::now();
        $date = $startsAt->setTimezone($business->timezone)->toDateString();

        $slot = collect($this->times->handle($business, $service, $staff, $date, $date, $now))
            ->first(fn ($slot) => $slot->start->getTimestamp() === $startsAt->getTimestamp());

        if ($slot === null) {
            throw TimeNotAvailable::notOffered();
        }

        foreach ($this->leastBusyFirst($business, $slot->staffIds, $date, $now) as $staffId) {
            try {
                return DB::transaction(fn () => $this->hold($business, $service, $staffId, $startsAt, $now));
            } catch (QueryException $e) {
                if ($e->getCode() !== Booking::OVERLAP_ERROR) {
                    throw $e;
                }
            }
        }

        throw TimeNotAvailable::taken();
    }

    private function hold(Business $business, Service $service, int $staffId, CarbonImmutable $startsAt, CarbonImmutable $now): Booking
    {
        $endsAt = $startsAt->addMinutes($service->duration_minutes);
        $blockedUntil = $endsAt->addMinutes($service->buffer_after_minutes);

        $this->clearLapsedHolds->handle($business->id, $staffId, $startsAt, $blockedUntil, $now);

        $booking = new Booking([
            'service_id' => $service->id,
            'staff_id' => $staffId,
            'service_name' => $service->name,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'blocked_until' => $blockedUntil,
            'status' => BookingStatus::Held,
            'hold_expires_at' => $now->addMinutes($business->hold_minutes),
            'source' => 'online',
            'price' => $service->price,
            'deposit' => $service->depositAmount(),
            'currency' => $business->currency,
        ]);
        $booking->business_id = $business->id;
        $booking->save();

        return $booking;
    }

    /**
     * "Anyone free" goes to whoever has the fewest bookings that day, so
     * work is shared out instead of always landing on the first person.
     *
     * @param  list<int>  $staffIds
     * @return list<int>
     */
    private function leastBusyFirst(Business $business, array $staffIds, string $date, CarbonImmutable $now): array
    {
        if (count($staffIds) < 2) {
            return $staffIds;
        }

        // A local day, which is 23 or 25 hours long when the clocks change.
        $dayStart = CarbonImmutable::parse($date, $business->timezone)->startOfDay();
        $dayEnd = $dayStart->addDay()->startOfDay();

        /** @var array<int, int> $counts */
        $counts = Booking::where('business_id', $business->id)
            ->whereIn('staff_id', $staffIds)
            ->takingTime($now)
            ->where('starts_at', '>=', $dayStart->utc())
            ->where('starts_at', '<', $dayEnd->utc())
            ->selectRaw('staff_id, count(*) as bookings')
            ->groupBy('staff_id')
            ->pluck('bookings', 'staff_id')
            ->all();

        $order = array_flip($staffIds);
        usort($staffIds, fn (int $a, int $b) => [($counts[$a] ?? 0), $order[$a]] <=> [($counts[$b] ?? 0), $order[$b]]);

        return $staffIds;
    }
}
