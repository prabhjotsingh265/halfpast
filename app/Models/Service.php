<?php

namespace App\Models;

use App\Enums\DepositType;
use App\Tenancy\BelongsToBusiness;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Something a business sells by the appointment: a haircut, a physio session.
 *
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property string|null $description
 * @property int $duration_minutes
 * @property int $buffer_after_minutes
 * @property int $price
 * @property DepositType $deposit_type
 * @property int $deposit_value
 * @property bool $is_active
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'description',
    'duration_minutes',
    'buffer_after_minutes',
    'price',
    'deposit_type',
    'deposit_value',
    'is_active',
    'position',
])]
class Service extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deposit_type' => DepositType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * The people who offer it.
     *
     * @return BelongsToMany<Staff, $this>
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class);
    }

    /**
     * The deposit a customer pays to book, in minor units. Never more than the price.
     */
    public function depositAmount(): int
    {
        return match ($this->deposit_type) {
            DepositType::None => 0,
            DepositType::Fixed => min($this->deposit_value, $this->price),
            DepositType::Percent => (int) round($this->price * min($this->deposit_value, 100) / 100),
        };
    }
}
