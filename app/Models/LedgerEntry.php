<?php

namespace App\Models;

use App\Tenancy\BelongsToBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money in (a deposit, positive) or out (a refund, negative). Never edited,
 * so a business's balance is the sum of its rows.
 *
 * @property int $id
 * @property int $business_id
 * @property int $payment_id
 * @property int|null $refund_id
 * @property string $type
 * @property int $amount
 * @property string $currency
 * @property string $gateway
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['payment_id', 'refund_id', 'type', 'amount', 'currency', 'gateway'])]
class LedgerEntry extends Model
{
    use BelongsToBusiness;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
