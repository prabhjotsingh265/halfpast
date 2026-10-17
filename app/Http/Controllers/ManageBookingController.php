<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Business;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A customer's own page for their booking, reached through the signed link
 * in their confirmation email. No account needed.
 */
class ManageBookingController extends Controller
{
    public function show(Business $business, Booking $booking): Response
    {
        $booking->load(['staff', 'customer']);

        return Inertia::render('booking/Manage', [
            'booking' => [
                'code' => $booking->displayCode(),
                'status' => $booking->status->value,
                'service_name' => $booking->service_name,
                'service_id' => $booking->service_id,
                'staff_name' => $booking->staff?->name,
                'starts_at' => $booking->starts_at->utc()->toIso8601ZuluString(),
                'ends_at' => $booking->ends_at->utc()->toIso8601ZuluString(),
                'price' => $booking->price,
                'deposit' => $booking->deposit,
                'currency' => $booking->currency,
                'customer_name' => $booking->customer?->name,
                'customer_email' => $booking->customer?->email,
                'notes' => $booking->notes,
                'calendar_url' => $booking->status->value === 'confirmed' ? $booking->calendarUrl() : null,
            ],
        ]);
    }

    /** The booking as a calendar file, for "Add to calendar". */
    public function calendar(Business $business, Booking $booking): HttpResponse
    {
        return response($booking->calendarEvent()->render(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="booking-'.$booking->code.'.ics"',
        ]);
    }
}
