<?php

namespace App\Actions\Staff;

use App\Models\Staff;
use App\Models\WorkingHour;
use Illuminate\Support\Facades\DB;

class SaveWeeklyHours
{
    /** What a new business starts with: Monday to Friday, 9 to 5. */
    public const OFFICE_HOURS = [
        ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00'],
        ['weekday' => 2, 'starts_at' => '09:00', 'ends_at' => '17:00'],
        ['weekday' => 3, 'starts_at' => '09:00', 'ends_at' => '17:00'],
        ['weekday' => 4, 'starts_at' => '09:00', 'ends_at' => '17:00'],
        ['weekday' => 5, 'starts_at' => '09:00', 'ends_at' => '17:00'],
    ];

    /**
     * Replace someone's whole week with the stretches given.
     *
     * @param  array<int, array{weekday: int, starts_at: string, ends_at: string}>  $hours
     */
    public function handle(Staff $staff, array $hours): void
    {
        DB::transaction(function () use ($staff, $hours) {
            WorkingHour::where('staff_id', $staff->id)->delete();

            WorkingHour::insert(array_map(fn (array $hour) => [
                'business_id' => $staff->business_id,
                'staff_id' => $staff->id,
                'weekday' => $hour['weekday'],
                'starts_at' => $hour['starts_at'],
                'ends_at' => $hour['ends_at'],
            ], array_values($hours)));
        });
    }
}
