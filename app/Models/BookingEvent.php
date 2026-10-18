<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing that happened to a booking: it was made, or its status changed.
 *
 * @property int $id
 * @property int $business_id
 * @property int $booking_id
 * @property string $type
 * @property int|null $user_id
 * @property array<string, mixed>|null $meta
 * @property CarbonImmutable $created_at
 */
class BookingEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'immutable_datetime'];
    }

    /**
     * Records an event for a booking, as whoever is logged in.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function record(Booking $booking, string $type, array $meta = []): void
    {
        self::create([
            'business_id' => $booking->business_id,
            'booking_id' => $booking->id,
            'type' => $type,
            'user_id' => auth()->id(),
            'meta' => $meta === [] ? null : $meta,
        ]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
