<?php

namespace Database\Factories;

use App\Enums\DepositType;
use App\Models\Business;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => fake()->randomElement(['Haircut', 'Colour', 'Blow-dry', 'Beard trim', 'Gloss']),
            'duration_minutes' => fake()->randomElement([30, 45, 60, 90]),
            'buffer_after_minutes' => 0,
            'price' => fake()->randomElement([2500, 3000, 4500, 6000]),
            'deposit_type' => DepositType::None,
            'deposit_value' => 0,
            'is_active' => true,
        ];
    }

    public function withPercentDeposit(int $percent): static
    {
        return $this->state(['deposit_type' => DepositType::Percent, 'deposit_value' => $percent]);
    }

    public function withFixedDeposit(int $amount): static
    {
        return $this->state(['deposit_type' => DepositType::Fixed, 'deposit_value' => $amount]);
    }
}
