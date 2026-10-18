<?php

namespace App\Http\Controllers;

use App\Actions\Booking\CancelBooking;
use App\Actions\Booking\RecordVisit;
use App\Enums\BookingStatus;
use App\Mail\Money;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * What the business does with a booking from its calendar: say how the
 * appointment went, or cancel it.
 */
class BookingActionsController extends Controller
{
    public function complete(Booking $booking, RecordVisit $visits): RedirectResponse
    {
        return $this->record($booking, BookingStatus::Completed, $visits);
    }

    public function noShow(Booking $booking, RecordVisit $visits): RedirectResponse
    {
        return $this->record($booking, BookingStatus::NoShow, $visits);
    }

    public function cancel(Request $request, Booking $booking, CancelBooking $cancel): RedirectResponse
    {
        Gate::authorize('cancel', $booking);

        $choice = $request->validate([
            'refund' => ['required', 'boolean'],
            'tell_customer' => ['required', 'boolean'],
        ]);

        if (! $cancel->handle($booking, 'business', refund: (bool) $choice['refund'], tellCustomer: (bool) $choice['tell_customer'])) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "This booking can't be cancelled now. It may have started or been cancelled already."]);

            return back();
        }

        $customer = $booking->customer?->name;

        Inertia::flash('toast', ['type' => 'success', 'message' => $booking->refund_due > 0
            ? 'Booking cancelled. '.Money::format($booking->refund_due, $booking->currency).' is on its way back'.($customer ? " to {$customer}." : '.')
            : 'Booking cancelled.',
        ]);

        return back();
    }

    private function record(Booking $booking, BookingStatus $outcome, RecordVisit $visits): RedirectResponse
    {
        Gate::authorize('mark', $booking);

        if (! $visits->handle($booking, $outcome)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $booking->starts_at->isFuture()
                ? 'You can say how it went once the appointment has started.'
                : "This booking can't be marked."]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $outcome === BookingStatus::Completed ? 'Marked as completed.' : 'Marked as a no-show.']);

        return back();
    }
}
