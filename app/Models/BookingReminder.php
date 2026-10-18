<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A reminder that went out for a booking: "24h" or "2h".
 *
 * @property int $id
 * @property int $business_id
 * @property int $booking_id
 * @property string $kind
 * @property CarbonImmutable $sent_at
 */
class BookingReminder extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sent_at' => 'immutable_datetime'];
    }
}
