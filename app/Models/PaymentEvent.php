<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A webhook as it arrived. Platform-wide rather than per business: the
 * business is only known once the event has been read.
 *
 * @property int $id
 * @property string $gateway
 * @property string $event_id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property CarbonImmutable|null $processed_at
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['gateway', 'event_id', 'type', 'payload', 'processed_at'])]
class PaymentEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
