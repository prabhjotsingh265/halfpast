<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = CarbonImmutable::now()->addDays(3)->setTime(10, 0);

        return [
            'business_id' => Business::factory(),
            'staff_id' => fn (array $attributes) => Staff::factory()->create(['business_id' => $attributes['business_id']])->id,
            'service_name' => 'Haircut',
            'starts_at' => $start,
            'ends_at' => $start->addMinutes(30),
            'blocked_until' => $start->addMinutes(30),
            'status' => BookingStatus::Confirmed,
            'source' => 'online',
            'price' => 3000,
            'deposit' => 0,
            'currency' => 'GBP',
        ];
    }

    /**
     * Exact times, in UTC: at('2026-11-02 10:00', '2026-11-02 10:30').
     */
    public function at(string $start, string $end, ?string $blockedUntil = null): static
    {
        return $this->state(fn () => [
            'starts_at' => CarbonImmutable::parse($start),
            'ends_at' => CarbonImmutable::parse($end),
            'blocked_until' => CarbonImmutable::parse($blockedUntil ?? $end),
        ]);
    }

    public function held(int $minutesLeft = 10): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Held,
            'hold_expires_at' => CarbonImmutable::now()->addMinutes($minutesLeft),
        ]);
    }
}
