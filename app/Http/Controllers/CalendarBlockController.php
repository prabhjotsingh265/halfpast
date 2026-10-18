<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Http\Requests\TimeOffRequest;
use App\Models\Booking;
use App\Models\Staff;
use App\Models\TimeOff;
use App\Tenancy\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Blocking out time from the calendar: a lunch break, a meeting, closing
 * early. It's time off under the hood, so online booking stops offering
 * those times. Bookings already in that time stay booked, and the message
 * says so, so they can be moved or cancelled by hand.
 */
class CalendarBlockController extends Controller
{
    public function store(TimeOffRequest $request, CurrentBusiness $current): RedirectResponse
    {
        $business = $current->require();
        $staffId = $request->validated('staff_id');
        $staff = $staffId === null ? null : Staff::find((int) $staffId);

        Gate::authorize('create', [TimeOff::class, $staff]);

        [$start, $end] = $request->period();

        TimeOff::create([
            'staff_id' => $staff?->id,
            'starts_at' => $start,
            'ends_at' => $end,
            'reason' => $request->validated('reason'),
        ]);

        $stillBooked = Booking::where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->when($staff !== null, fn ($query) => $query->where('staff_id', $staff?->id))
            ->where(fn ($query) => $query
                ->where('status', BookingStatus::Confirmed)
                ->orWhere(fn ($query) => $query->where('status', BookingStatus::Held)->where('hold_expires_at', '>', now())))
            ->count();

        $zone = $business->timezone;
        $what = $staff === null ? "{$business->name} is closed" : "{$staff->name} is blocked out";
        $when = $start->setTimezone($zone)->format('g:i a').' to '.$end->setTimezone($zone)->format('g:i a');

        Inertia::flash('toast', $stillBooked === 0
            ? ['type' => 'success', 'message' => "{$what} from {$when}."]
            : ['type' => 'warning', 'message' => "{$what} from {$when}. ".($stillBooked === 1 ? '1 booking is' : "{$stillBooked} bookings are").' still in that time: move or cancel them if needed.']);

        return redirect()->route('calendar', ['date' => $start->setTimezone($zone)->toDateString()]);
    }

    public function destroy(TimeOff $timeOff): RedirectResponse
    {
        Gate::authorize('delete', $timeOff);

        $timeOff->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'That time is free again.']);

        return back();
    }
}
