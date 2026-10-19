<?php

namespace App\Support;

use App\Models\Business;
use App\Models\Staff;
use App\Models\WorkingHour;

/**
 * When a business is open: whenever anyone taking bookings is working. Each
 * weekday's stretches across the team, joined where they overlap, as local
 * clock times ("09:00" to "17:30").
 */
final class OpeningHours
{
    /**
     * @return array<int, list<array{string, string}>> ISO weekday (1 is Monday) => stretches
     */
    public static function week(Business $business): array
    {
        $active = Staff::withoutGlobalScopes()->where('business_id', $business->id)->where('is_active', true)->pluck('id');
        $rows = WorkingHour::withoutGlobalScopes()->whereIn('staff_id', $active)->orderBy('starts_at')->get()->groupBy('weekday');
        $week = [];

        foreach (range(1, 7) as $weekday) {
            $merged = [];

            foreach ($rows->get($weekday, collect()) as $hour) {
                [$from, $to] = [substr($hour->starts_at, 0, 5), substr($hour->ends_at, 0, 5)];
                $last = array_key_last($merged);

                if ($last !== null && $from <= $merged[$last][1]) {
                    $merged[$last][1] = max($merged[$last][1], $to);
                } else {
                    $merged[] = [$from, $to];
                }
            }

            $week[$weekday] = $merged;
        }

        return $week;
    }
}
