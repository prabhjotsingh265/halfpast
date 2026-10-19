<?php

namespace App\Actions\Businesses;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Business;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a business and everything in it, for good. Not while customers
 * still have bookings to come (cancel them first, so they're told and get
 * their deposits back), and never a demo business.
 */
class DeleteBusiness
{
    /**
     * @throws ValidationException
     */
    public function handle(Business $business): void
    {
        if ($business->is_demo) {
            throw ValidationException::withMessages(['name' => "Demo businesses can't be deleted. They're rebuilt every night anyway."]);
        }

        $upcoming = Booking::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('starts_at', '>', now())
            ->where(fn ($query) => $query
                ->where('status', BookingStatus::Confirmed)
                ->orWhere(fn ($query) => $query->where('status', BookingStatus::Held)->where('hold_expires_at', '>', now())))
            ->count();

        if ($upcoming > 0) {
            throw ValidationException::withMessages(['name' => ($upcoming === 1 ? 'There is 1 booking still to come. Cancel it' : "There are {$upcoming} bookings still to come. Cancel them").' from the calendar first, so customers are told and get their deposits back.']);
        }

        Storage::disk('public')->deleteDirectory("staff/{$business->id}");
        Storage::disk('public')->deleteDirectory("logos/{$business->id}");
        Storage::disk('public')->deleteDirectory("share/{$business->id}");

        // Services, staff, customers, bookings, payments and the rest go with it.
        $business->delete();
    }
}
