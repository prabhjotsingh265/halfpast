<?php

namespace App\Policies;

use App\Enums\BusinessRole;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Staff;
use App\Models\User;

/**
 * Who can act on bookings from the dashboard. Owners can do anything;
 * staff can add bookings to their own diary and say how their own
 * appointments went. Cancelling moves money, so it's for owners only.
 */
class BookingPolicy
{
    public function create(User $user, Staff $staff): bool
    {
        return self::isOwner($user, $staff->business_id) || ($staff->user_id !== null && $staff->user_id === $user->id);
    }

    public function mark(User $user, Booking $booking): bool
    {
        return self::isOwner($user, $booking->business_id)
            || ($booking->staff_id !== null && Staff::whereKey($booking->staff_id)->where('user_id', $user->id)->exists());
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return self::isOwner($user, $booking->business_id);
    }

    private static function isOwner(User $user, int $businessId): bool
    {
        $business = Business::find($businessId);

        return $business !== null && $user->roleIn($business) === BusinessRole::Owner;
    }
}
