<?php

namespace App\Models;

use App\Enums\BusinessRole;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $timezone
 * @property string $currency
 * @property string|null $country
 * @property int $slot_step_minutes
 * @property int $min_notice_minutes
 * @property int $booking_horizon_days
 * @property int $cancellation_window_hours
 * @property int $hold_minutes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'slug',
    'timezone',
    'currency',
    'country',
    'slot_step_minutes',
    'min_notice_minutes',
    'booking_horizon_days',
    'cancellation_window_hours',
    'hold_minutes',
])]
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    /**
     * Everyone who works here, with their role.
     *
     * @return BelongsToMany<User, $this, Membership>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(Membership::class)
            ->withPivot('id', 'role')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<User, $this, Membership>
     */
    public function owners(): BelongsToMany
    {
        return $this->members()->wherePivot('role', BusinessRole::Owner->value);
    }

    public function addMember(User $user, BusinessRole $role): void
    {
        $this->members()->syncWithoutDetaching([$user->id => ['role' => $role->value]]);
    }
}
