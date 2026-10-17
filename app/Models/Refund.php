<?php

namespace App\Models;

use App\Enums\RefundStatus;
use App\Tenancy\BelongsToBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money going back to a customer, retried until the gateway accepts it.
 *
 * @property int $id
 * @property int $business_id
 * @property int $payment_id
 * @property int $amount
 * @property RefundStatus $status
 * @property string $reason
 * @property string|null $gateway_refund_id
 * @property int $attempts
 * @property string|null $last_error
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['payment_id', 'amount', 'status', 'reason', 'gateway_refund_id', 'attempts', 'last_error'])]
class Refund extends Model
{
    use BelongsToBusiness;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
