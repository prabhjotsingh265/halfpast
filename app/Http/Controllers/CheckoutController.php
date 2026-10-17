<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Business;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The step after holding a time: the customer's details, and (for services
 * that take one) the deposit. Only the browser that made the hold can see it.
 */
class CheckoutController extends Controller
{
    public function show(Request $request, Business $business, Booking $booking): Response
    {
        abort_unless(HoldController::heldBy($request, $booking), 404);

        $booking->load('staff');

        return Inertia::render('booking/Checkout', [
            'booking' => [
                'code' => $booking->code,
                'service_name' => $booking->service_name,
                'service_id' => $booking->service_id,
                'staff_name' => $booking->staff?->name,
                'starts_at' => $booking->starts_at->utc()->toIso8601ZuluString(),
                'ends_at' => $booking->ends_at->utc()->toIso8601ZuluString(),
                'price' => $booking->price,
                'deposit' => $booking->deposit,
                'currency' => $booking->currency,
                'held' => $booking->isHeld(),
                'confirmed' => $booking->status === BookingStatus::Confirmed,
                'hold_expires_at' => $booking->hold_expires_at?->utc()->toIso8601ZuluString(),
            ],
            'holdMinutes' => $business->hold_minutes,
            // The browser's clock may be off; the countdown runs from the server's.
            'serverNow' => now()->utc()->toIso8601ZuluString(),
        ]);
    }
}
