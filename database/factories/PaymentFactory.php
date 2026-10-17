<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'business_id' => fn (array $attributes) => Booking::withoutGlobalScopes()->whereKey($attributes['booking_id'])->value('business_id'),
            'gateway' => 'test',
            'amount' => 2000,
            'currency' => 'GBP',
            'status' => PaymentStatus::Pending,
            'gateway_order_id' => 'test_'.Str::random(16),
        ];
    }
}
