<?php

namespace App\Models;

use App\Enums\BusinessRole;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description A line or two for the booking page.
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $website
 * @property string|null $logo_path On the public disk; see SaveBusinessLogo.
 * @property string $slug
 * @property bool $is_demo One of the demo businesses, rebuilt every night.
 * @property string $timezone
 * @property string $currency
 * @property string|null $country
 * @property int $slot_step_minutes
 * @property int $min_notice_minutes
 * @property int $booking_horizon_days
 * @property int $cancellation_window_hours
 * @property int $hold_minutes
 * @property list<string> $payment_gateways
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'description',
    'address',
    'phone',
    'website',
    'slug',
    'timezone',
    'currency',
    'country',
    'slot_step_minutes',
    'min_notice_minutes',
    'booking_horizon_days',
    'cancellation_window_hours',
    'hold_minutes',
    'payment_gateways',
])]
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    /** Currencies a business can price in. */
    public const CURRENCIES = ['GBP', 'USD', 'EUR', 'INR', 'AUD', 'CAD'];

    /**
     * The booking rules' defaults, the same as the table's, so a business
     * made in code has them before it's ever reloaded from the database.
     *
     * @var array<string, int|string>
     */
    protected $attributes = [
        'slot_step_minutes' => 15,
        'min_notice_minutes' => 120,
        'booking_horizon_days' => 60,
        'cancellation_window_hours' => 24,
        'hold_minutes' => 15,
        'payment_gateways' => '[]',
    ];

    /** The logo's address, or null for the initial in a coloured square. */
    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

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

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * The people customers can book.
     *
     * @return HasMany<Staff, $this>
     */
    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_gateways' => 'array',
            'is_demo' => 'boolean',
        ];
    }

    /** Booking pages are addressed by slug: /glow-studio. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function addMember(User $user, BusinessRole $role): void
    {
        $this->members()->syncWithoutDetaching([$user->id => ['role' => $role->value]]);
    }
}
