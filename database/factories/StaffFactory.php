<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Staff;
use App\Models\WorkingHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => fake()->firstName(),
            'is_active' => true,
        ];
    }

    /**
     * Monday to Friday, 9 to 5 with a lunch hour.
     */
    public function withOfficeHours(): static
    {
        return $this->afterCreating(function (Staff $staff) {
            foreach (range(1, 5) as $weekday) {
                foreach ([['09:00', '13:00'], ['14:00', '17:00']] as [$start, $end]) {
                    $hour = new WorkingHour(['staff_id' => $staff->id, 'weekday' => $weekday, 'starts_at' => $start, 'ends_at' => $end]);
                    $hour->business_id = $staff->business_id;
                    $hour->save();
                }
            }
        });
    }
}
