<?php

namespace App\Http\Controllers;

use App\Actions\Booking\HoldTime;
use App\Enums\BookingStatus;
use App\Exceptions\TimeNotAvailable;
use App\Http\Requests\HoldTimeRequest;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Service;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Holding a time from the booking page, and letting it go again.
 *
 * The codes of the holds a browser made are kept in its session, and only
 * that browser can carry on with them. Each browser holds one time at once:
 * picking another lets the first go.
 */
class HoldController extends Controller
{
    public const SESSION_KEY = 'booking.holds';

    public function store(HoldTimeRequest $request, Business $business, HoldTime $hold): RedirectResponse
    {
        $service = Service::findOrFail((int) $request->validated('service_id'));
        $staffId = $request->validated('staff_id');
        $staff = $staffId === null ? null : Staff::findOrFail((int) $staffId);
        $startsAt = $request->startsAt();

        // Pressing "Hold" again for the time they already have.
        $existing = $this->heldNow($request)->first(fn (Booking $booking) => $booking->service_id === $service->id
            && $booking->starts_at->equalTo($startsAt)
            && ($staff === null || $booking->staff_id === $staff->id));

        if ($existing !== null) {
            return redirect()->route('booking.checkout', [$business, $existing]);
        }

        // The old time is let go and the new one held in one transaction:
        // the old hold mustn't block the new one (its free time after may
        // overlap), and if the new time is taken it comes back untouched.
        $previous = (array) $request->session()->get(self::SESSION_KEY, []);

        try {
            $booking = DB::transaction(function () use ($previous, $hold, $business, $service, $staff, $startsAt) {
                self::expire($previous);

                return $hold->handle($business, $service, $staff, $startsAt);
            });
        } catch (TimeNotAvailable $e) {
            return back()->withErrors(['starts_at' => $e->getMessage()]);
        }

        $request->session()->put(self::SESSION_KEY, [$booking->code]);

        return redirect()->route('booking.checkout', [$business, $booking]);
    }

    public function destroy(Request $request, Business $business, Booking $booking): RedirectResponse
    {
        abort_unless(self::heldBy($request, $booking), 404);

        if ($booking->status === BookingStatus::Held) {
            $booking->update(['status' => BookingStatus::Expired]);
        }

        return redirect()->route('booking.show', [
            'business' => $business,
            'service' => $booking->service_id,
            'date' => $booking->starts_at->setTimezone($business->timezone)->toDateString(),
        ]);
    }

    public static function heldBy(Request $request, Booking $booking): bool
    {
        return in_array($booking->code, (array) $request->session()->get(self::SESSION_KEY, []), true);
    }

    /**
     * This browser's holds that haven't run out.
     *
     * @return Collection<int, Booking>
     */
    private function heldNow(Request $request): Collection
    {
        $codes = (array) $request->session()->get(self::SESSION_KEY, []);

        return $codes === []
            ? new Collection
            : Booking::whereIn('code', $codes)->takingTime()->where('status', BookingStatus::Held)->get();
    }

    /**
     * @param  array<mixed>  $codes
     */
    private static function expire(array $codes): void
    {
        if ($codes !== []) {
            Booking::whereIn('code', $codes)
                ->where('status', BookingStatus::Held)
                ->update(['status' => BookingStatus::Expired]);
        }
    }
}
