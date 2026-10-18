<?php

namespace App\Models;

use App\Tenancy\BelongsToBusiness;
use Database\Factories\StaffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Someone customers can book: a stylist, a physio, a tutor.
 *
 * @property int $id
 * @property int $business_id
 * @property int|null $user_id
 * @property string|null $photo_path A square WebP on the public disk.
 * @property string $name
 * @property string|null $bio
 * @property bool $is_active
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'bio', 'is_active', 'position', 'user_id'])]
class Staff extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<StaffFactory> */
    use HasFactory;

    protected $table = 'staff';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    /**
     * @return HasMany<WorkingHour, $this>
     */
    public function workingHours(): HasMany
    {
        return $this->hasMany(WorkingHour::class)->orderBy('weekday')->orderBy('starts_at');
    }

    /**
     * @return HasMany<TimeOff, $this>
     */
    public function timeOff(): HasMany
    {
        return $this->hasMany(TimeOff::class);
    }

    /** Where the photo can be loaded from, or null to show initials. */
    public function photoUrl(): ?string
    {
        return $this->photo_path === null ? null : Storage::disk('public')->url($this->photo_path);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
