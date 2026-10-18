<?php

namespace App\Models;

use App\Observers\TimeOffObserver;
use App\Tenancy\BelongsToBusiness;
use Carbon\CarbonImmutable;
use Database\Factories\TimeOffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stretch when someone (or, with no staff member, the whole business) isn't
 * available: a holiday, a training day. Stored as real moments in UTC.
 *
 * @property int $id
 * @property int $business_id
 * @property int|null $staff_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property string|null $reason
 */
#[ObservedBy(TimeOffObserver::class)]
#[Fillable(['staff_id', 'starts_at', 'ends_at', 'reason'])]
class TimeOff extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<TimeOffFactory> */
    use HasFactory;

    protected $table = 'time_off';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
