<?php

namespace App\Http\Controllers;

use App\Actions\Booking\AddBooking;
use App\Actions\Booking\FindBookableTimes;
use App\Actions\Booking\FindOrAddCustomer;
use App\Http\Requests\NewBookingRequest;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use App\Models\WorkingHour;
use App\Tenancy\CurrentBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Adding a booking by hand, from the calendar: the free times to suggest,
 * customers already on file, and saving it.
 */
class NewBookingController extends Controller
{
    /**
     * Free start times for a person on a day, ignoring the online notice and
     * how-far-ahead rules, plus their working hours that day.
     */
    public function times(Request $request, CurrentBusiness $current, FindBookableTimes $finder): JsonResponse
    {
        $business = $current->require();
        $input = $request->validate([
            'service' => ['required', 'integer'],
            'staff' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $service = Service::findOrFail($request->integer('service'));
        $staff = Staff::findOrFail($request->integer('staff'));
        $date = (string) $input['date'];

        $slots = $finder->handle($business, $service, $staff, $date, $date, byBusiness: true);

        return response()->json([
            'times' => collect($slots)->map(fn ($slot) => CarbonImmutable::instance($slot->start)->utc()->toIso8601ZuluString())->values(),
            'hours' => WorkingHour::where('staff_id', $staff->id)
                ->where('weekday', CarbonImmutable::parse($date)->isoWeekday())
                ->orderBy('starts_at')
                ->get()
                ->map(fn (WorkingHour $hour) => [WorkingHour::minutesOf($hour->starts_at), WorkingHour::minutesOf($hour->ends_at)])
                ->values(),
        ]);
    }

    /** Customers on file matching a name, email or phone number. */
    public function customers(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));

        if (mb_strlen($search) < 2) {
            return response()->json([]);
        }

        $customers = Customer::matching($search)
            ->whereNull('forgotten_at')
            ->orderBy('name')
            ->limit(6)
            ->get(['id', 'name', 'email', 'phone']);

        return response()->json($customers);
    }

    public function store(NewBookingRequest $request, CurrentBusiness $current, AddBooking $add, FindOrAddCustomer $customers): RedirectResponse
    {
        $business = $current->require();

        $service = Service::find($request->integer('service')) ?? throw ValidationException::withMessages(['service' => 'Pick a service.']);
        $staff = Staff::find($request->integer('staff')) ?? throw ValidationException::withMessages(['staff' => 'Pick who it is with.']);

        Gate::authorize('create', [Booking::class, $staff]);

        $date = (string) $request->validated('date');
        $startsAt = CarbonImmutable::parse("{$date} {$request->validated('time')}", $business->timezone);

        $booking = DB::transaction(fn () => $add->handle(
            $business,
            $service,
            $staff,
            $startsAt,
            $customers->handle(
                $request->filled('customer_id') ? $request->integer('customer_id') : null,
                $request->validated('name'),
                $request->validated('email'),
                $request->validated('phone'),
            ),
            $request->validated('notes'),
            $request->boolean('tell_customer', true),
        ));

        $when = $booking->starts_at->setTimezone($business->timezone);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Booked: {$service->name} with {$staff->name}, {$when->format('D j M')} at {$when->format('g:i a')}."]);

        return redirect()->route('calendar', ['date' => $date]);
    }
}
