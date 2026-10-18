<?php

namespace App\Policies;

use App\Enums\BusinessRole;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Staff;
use App\Models\User;

/**
 * Who can act on a booking from the dashboard. Owners can do anything;
 * staff can say how their own appointments went. Cancelling moves money,
 * so it's for owners only.
 */
class BookingPolicy
{
    public function mark(User $user, Booking $booking): bool
    {
        return self::isOwner($user, $booking)
            || ($booking->staff_id !== null && Staff::whereKey($booking->staff_id)->where('user_id', $user->id)->exists());
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return self::isOwner($user, $booking);
    }

    private static function isOwner(User $user, Booking $booking): bool
    {
        $business = Business::find($booking->business_id);

        return $business !== null && $user->roleIn($business) === BusinessRole::Owner;
    }
}
