<?php

namespace App\Models;

use App\Enums\PaymentOutcome;
use App\Enums\PaymentStatus;
use App\Tenancy\BelongsToBusiness;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One attempt to pay a booking's deposit through a gateway.
 *
 * @property int $id
 * @property int $business_id
 * @property int $booking_id
 * @property string $gateway
 * @property int $amount
 * @property string $currency
 * @property PaymentStatus $status
 * @property PaymentOutcome|null $outcome
 * @property string|null $gateway_order_id
 * @property string|null $gateway_payment_id
 * @property CarbonImmutable|null $authorized_at
 * @property CarbonImmutable|null $captured_at
 * @property CarbonImmutable|null $released_at
 * @property int $refunded_amount
 * @property string|null $release_reason
 * @property string|null $failure_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'booking_id',
    'gateway',
    'amount',
    'currency',
    'status',
    'outcome',
    'gateway_order_id',
    'gateway_payment_id',
    'authorized_at',
    'captured_at',
    'released_at',
    'refunded_amount',
    'release_reason',
    'failure_reason',
])]
class Payment extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'outcome' => PaymentOutcome::class,
            'authorized_at' => 'immutable_datetime',
            'captured_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /** What's been taken and not yet given back, in minor units. */
    public function refundable(): int
    {
        return $this->captured_at === null ? 0 : $this->amount - $this->refunded_amount;
    }
}
