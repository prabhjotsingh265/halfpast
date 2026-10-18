<?php

namespace App\Models;

use App\Tenancy\BelongsToBusiness;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $notes
 * @property Carbon|null $forgotten_at When their details were removed at their request.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'phone', 'notes'])]
class Customer extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['forgotten_at' => 'datetime'];
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Customers whose name, email or phone number matches what was typed.
     * Phone numbers match on their digits, however they were written.
     *
     * @param  Builder<Customer>  $query
     */
    public function scopeMatching(Builder $query, string $search): void
    {
        $like = '%'.addcslashes(mb_strtolower(trim($search)), '%_\\').'%';
        $digits = (string) preg_replace('/\D/', '', $search);

        $query->where(fn (Builder $query) => $query
            ->whereRaw('lower(name) like ?', [$like])
            ->orWhereRaw('lower(email) like ?', [$like])
            ->when(strlen($digits) >= 3, fn (Builder $query) => $query->orWhereRaw("regexp_replace(coalesce(phone, ''), '\\D', '', 'g') like ?", ['%'.$digits.'%'])));
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null || trim($value) === '' ? null : mb_strtolower(trim($value)),
        );
    }
}
