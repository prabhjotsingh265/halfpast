<?php

namespace App\Observers;

use App\Events\CalendarChanged;
use App\Models\TimeOff;

/**
 * Tells open calendars when time off or a closure is added, changed or
 * removed.
 */
class TimeOffObserver
{
    public function saved(TimeOff $timeOff): void
    {
        CalendarChanged::between($timeOff->business_id, $timeOff->starts_at, $timeOff->ends_at);
    }

    public function deleted(TimeOff $timeOff): void
    {
        CalendarChanged::between($timeOff->business_id, $timeOff->starts_at, $timeOff->ends_at);
    }
}
