<?php

namespace App\Policies;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\Staff;
use App\Models\TimeOff;
use App\Models\User;
use App\Tenancy\CurrentBusiness;

/**
 * Who can block out time from the calendar. Owners can for anyone, or close
 * the whole business; staff can block out (and free up) their own time.
 */
class TimeOffPolicy
{
    public function create(User $user, ?Staff $staff = null): bool
    {
        return self::isOwner($user) || ($staff?->user_id !== null && $staff->user_id === $user->id);
    }

    public function delete(User $user, TimeOff $timeOff): bool
    {
        return self::isOwner($user)
            || ($timeOff->staff_id !== null && Staff::whereKey($timeOff->staff_id)->where('user_id', $user->id)->exists());
    }

    private static function isOwner(User $user): bool
    {
        $business = app(CurrentBusiness::class)->get();

        return $business instanceof Business && $user->roleIn($business) === BusinessRole::Owner;
    }
}
