<?php

namespace Database\Factories;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'timezone' => 'Europe/London',
            'currency' => 'GBP',
            'country' => 'GB',
        ];
    }

    /**
     * Give the business an owner (a new user unless one is passed).
     */
    public function ownedBy(?User $owner = null): static
    {
        return $this->afterCreating(function (Business $business) use ($owner) {
            $business->addMember($owner ?? User::factory()->create(), BusinessRole::Owner);
        });
    }
}
