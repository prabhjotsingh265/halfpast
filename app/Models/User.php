<?php

namespace App\Models;

use App\Enums\BusinessRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property bool $is_demo A one-click demo login, rebuilt every night.
 * @property bool $is_admin Runs the platform: can open /admin.
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /** The people who run the platform: never a shared demo login, whatever its flags say. */
    public function isPlatformAdmin(): bool
    {
        return $this->is_admin && ! $this->is_demo;
    }

    /** Only platform admins get into /admin. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isPlatformAdmin();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_demo' => 'boolean',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * The businesses this person works at, with their role in each.
     *
     * @return BelongsToMany<Business, $this, Membership>
     */
    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class)
            ->using(Membership::class)
            ->withPivot('id', 'role')
            ->withTimestamps();
    }

    public function roleIn(Business $business): ?BusinessRole
    {
        $membership = $this->businesses()->whereKey($business->id)->first()?->pivot;

        return $membership instanceof Membership ? $membership->role : null;
    }
}
