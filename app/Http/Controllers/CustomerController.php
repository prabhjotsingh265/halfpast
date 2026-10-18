<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Support\Calendar\NewBookingOptions;
use App\Support\Calendar\Viewer;
use App\Tenancy\CurrentBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The business's customers: everyone who has booked, found by name, email
 * or phone, each with their whole booking history and the owner's notes.
 */
class CustomerController extends Controller
{
    public const SORTS = ['recent', 'name', 'visits'];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search'));
        $sort = in_array($request->query('sort'), self::SORTS, true) ? (string) $request->query('sort') : 'recent';
        $now = CarbonImmutable::now();

        $customers = Customer::query()
            ->whereNull('forgotten_at')
            ->when(mb_strlen($search) >= 2, fn (Builder $query) => $query->matching($search))
            ->withCount([
                'bookings as visits' => fn (Builder $query) => $query->where('status', BookingStatus::Completed),
                'bookings as no_shows' => fn (Builder $query) => $query->where('status', BookingStatus::NoShow),
            ])
            ->withMax(['bookings as last_seen_at' => fn (Builder $query) => $query
                ->whereIn('status', [BookingStatus::Completed, BookingStatus::Confirmed, BookingStatus::NoShow])
                ->where('starts_at', '<=', $now)], 'starts_at')
            ->withMin(['bookings as next_at' => fn (Builder $query) => $query
                ->where('status', BookingStatus::Confirmed)
                ->where('starts_at', '>', $now)], 'starts_at')
            ->when($sort === 'name', fn (Builder $query) => $query->orderBy('name'))
            ->when($sort === 'visits', fn (Builder $query) => $query->orderByDesc('visits')->orderBy('name'))
            // Seen most recently first; people who've never been yet go by when they were added.
            ->when($sort === 'recent', fn (Builder $query) => $query->orderByRaw('last_seen_at desc nulls last')->orderByDesc('created_at'))
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('customers/Index', [
            'customers' => $customers->through(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'visits' => (int) $customer->getAttribute('visits'),
                'no_shows' => (int) $customer->getAttribute('no_shows'),
                'last_seen_at' => self::iso($customer->getAttribute('last_seen_at')),
                'next_at' => self::iso($customer->getAttribute('next_at')),
            ]),
            'filters' => ['search' => $search, 'sort' => $sort],
            'total' => Customer::whereNull('forgotten_at')->count(),
        ]);
    }

    public function show(Request $request, Customer $customer, CurrentBusiness $current): Response
    {
        $business = $current->require();
        $viewer = new Viewer($business, $request->user());

        $bookings = $customer->bookings()
            ->with(['customer', 'payments', 'staff'])
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Completed, BookingStatus::NoShow, BookingStatus::Cancelled])
            ->orderByDesc('starts_at')
            ->get();

        $paid = Payment::whereIn('booking_id', $bookings->pluck('id'))
            ->whereIn('status', [PaymentStatus::Captured, PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded])
            ->get();

        return Inertia::render('customers/Show', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'notes' => $customer->notes,
                'since' => self::iso($customer->created_at),
                'forgotten' => $customer->forgotten_at !== null,
            ],
            'stats' => [
                'visits' => $bookings->where('status', BookingStatus::Completed)->count(),
                'upcoming' => $bookings->where('status', BookingStatus::Confirmed)->filter(fn (Booking $booking) => $booking->starts_at->isFuture())->count(),
                'noShows' => $bookings->where('status', BookingStatus::NoShow)->count(),
                'cancelled' => $bookings->where('status', BookingStatus::Cancelled)->count(),
                // Deposits taken, less anything refunded.
                'depositsKept' => (int) $paid->sum(fn (Payment $payment) => $payment->amount - $payment->refunded_amount),
                'currency' => $business->currency,
            ],
            'bookings' => $bookings->map(fn (Booking $booking) => $viewer->booking($booking) + [
                'staff_name' => $booking->staff?->name,
            ])->values(),
            'timezone' => $business->timezone,
            'today' => CarbonImmutable::now($business->timezone)->toDateString(),
            'newBooking' => NewBookingOptions::for($viewer),
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        abort_if($customer->forgotten_at !== null, 404);

        $details = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'nullable', 'string', 'email', 'max:255',
                Rule::unique('customers', 'email')
                    ->where('business_id', $customer->business_id)
                    ->ignore($customer->id),
            ],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{6,30}$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'email.unique' => 'Another customer already has that email address.',
            'phone.regex' => 'Use digits, spaces and + ( ) - only.',
        ]);

        $customer->update([
            'name' => trim($details['name']),
            'email' => $details['email'] ?? null,
            'phone' => self::blankToNull($details['phone'] ?? null),
            'notes' => self::blankToNull($details['notes'] ?? null),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Saved.']);

        return back();
    }

    /**
     * Removes what identifies a customer, at their request. Their bookings
     * stay for the business's records, with no name, contact details or
     * notes. Anyone with an appointment still to come has to have it
     * cancelled first, so nobody turns up to a booking nobody can trace.
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        abort_if($customer->forgotten_at !== null, 404);

        $upcoming = $customer->bookings()
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Held])
            ->where('starts_at', '>', now())
            ->exists();

        if ($upcoming) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'They have a booking still to come. Cancel it first, then remove their details.']);

            return back();
        }

        DB::transaction(function () use ($customer) {
            $customer->bookings()->update(['notes' => null]);
            $customer->update(['name' => 'Removed customer', 'email' => null, 'phone' => null, 'notes' => null]);
            $customer->forceFill(['forgotten_at' => now()])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Their details are removed. Their past bookings stay, without anything personal.']);

        return redirect()->route('customers.index');
    }

    private static function iso(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse($value)->utc()->toIso8601ZuluString();
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
