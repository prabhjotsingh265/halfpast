<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessProfileRequest;
use App\Tenancy\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What the booking page says about the business: its name, a line or two
 * about it, where it is, how to reach it, and its logo.
 */
class ProfileController extends Controller
{
    public function edit(CurrentBusiness $current): Response
    {
        $business = $current->require();

        return Inertia::render('business/Profile', [
            'profile' => $business->only(['name', 'description', 'address', 'phone', 'website']),
            'logoUrl' => $business->logoUrl(),
            'bookingUrl' => route('booking.show', ['business' => $business->slug]),
        ]);
    }

    public function update(BusinessProfileRequest $request, CurrentBusiness $current): RedirectResponse
    {
        $current->require()->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Booking page details saved.']);

        return redirect()->route('business.profile.edit');
    }
}
