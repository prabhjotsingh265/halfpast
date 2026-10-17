<?php

namespace App\Http\Controllers;

use App\Actions\Booking\CancelBooking;
use App\Actions\Booking\CancellationTerms;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Refund;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\URL;
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
        $terms = CancellationTerms::for($booking, $business);

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
                'cancelled_by' => $booking->cancelled_by,
                'refund_due' => $booking->refund_due,
                // Whether the gateway has done the refund yet.
                'refunded' => $booking->refund_due > 0 && Refund::whereIn('payment_id', $booking->payments()->select('id'))
                    ->whereIn('reason', ['customer_cancelled', 'business_cancelled'])
                    ->where('status', 'succeeded')
                    ->exists(),
            ],
            'cancellation' => $terms->allowed ? [
                'refund' => $terms->refund,
                'kept' => $terms->kept,
                'free_until' => $terms->freeUntil->utc()->toIso8601ZuluString(),
                'window_hours' => $business->cancellation_window_hours,
                'url' => URL::signedRoute('booking.cancel', ['business' => $business, 'booking' => $booking->code]),
            ] : null,
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

    public function cancel(Business $business, Booking $booking, CancelBooking $cancel): RedirectResponse
    {
        if (! $cancel->handle($booking, 'customer')) {
            return redirect()->to($booking->manageUrl())
                ->withErrors(['booking' => 'This booking can no longer be cancelled online. Please contact '.$business->name.'.']);
        }

        return redirect()->to($booking->manageUrl());
    }
}
