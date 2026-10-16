<?php

namespace App\Models;

use App\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stretch of a staff member's week, as local clock times: Tuesday 09:00–13:00.
 *
 * @property int $id
 * @property int $business_id
 * @property int $staff_id
 * @property int $weekday ISO: 1 is Monday, 7 is Sunday
 * @property string $starts_at "HH:MM:SS"
 * @property string $ends_at "HH:MM:SS"
 */
#[Fillable(['staff_id', 'weekday', 'starts_at', 'ends_at'])]
class WorkingHour extends Model
{
    use BelongsToBusiness;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /** "09:00:00" → 540 */
    public static function minutesOf(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
