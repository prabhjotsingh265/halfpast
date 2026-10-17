<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingRulesRequest;
use App\Tenancy\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * How far ahead customers can book, how long a picked time is held, and
 * when cancelling stops refunding the deposit.
 */
class BookingRulesController extends Controller
{
    public function edit(CurrentBusiness $current): Response
    {
        $business = $current->require();

        return Inertia::render('business/BookingRules', [
            'rules' => $business->only([
                'hold_minutes',
                'min_notice_minutes',
                'booking_horizon_days',
                'slot_step_minutes',
                'cancellation_window_hours',
            ]),
            'steps' => BookingRulesRequest::STEPS,
        ]);
    }

    public function update(BookingRulesRequest $request, CurrentBusiness $current): RedirectResponse
    {
        $current->require()->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Booking rules saved.']);

        return redirect()->route('business.rules.edit');
    }
}
