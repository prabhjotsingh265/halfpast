<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\TimeOff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeOff>
 */
class TimeOffFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDays(7)->startOfDay();

        return [
            'business_id' => Business::factory(),
            'staff_id' => null,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addDay(),
            'reason' => 'Holiday',
        ];
    }
}
