<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Service;
use App\Models\Staff;
use App\Models\TimeOff;
use App\Payments\Gateways;
use App\Support\Calendar\Viewer;
use App\Tenancy\CurrentBusiness;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Today, at a glance: who's coming in, what's next, what's left to take on
 * the day, and anything that needs a look. Owners see the whole business;
 * staff see their own diary.
 */
class DashboardController extends Controller
{
    /** How far back an appointment still waiting to be marked is worth a reminder. */
    private const UNMARKED_DAYS = 14;

    public function __invoke(Request $request, CurrentBusiness $current, Gateways $gateways): Response
    {
        $business = $current->require();
        $zone = $business->timezone;
        $now = CarbonImmutable::now();
        $today = $now->setTimezone($zone)->toDateString();
        $dayStart = CarbonImmutable::parse($today, $zone)->startOfDay();
        $dayEnd = $dayStart->addDay()->startOfDay();
        $weekStart = $dayStart->startOfWeek(CarbonInterface::MONDAY);
        $weekEnd = $weekStart->addDays(7)->startOfDay();

        $viewer = new Viewer($business, $request->user());

        /** Owners see everyone's bookings; staff, their own. */
        $mine = fn (Builder $query) => $viewer->isOwner ? $query : $query->whereIn('staff_id', $viewer->staffIds);

        $todays = Booking::with(['customer', 'payments'])
            ->shownOnCalendar()
            ->where('starts_at', '>=', $dayStart->utc())
            ->where('starts_at', '<', $dayEnd->utc())
            ->tap($mine)
            ->orderBy('starts_at')
            ->get();

        $booked = $todays->where('status', '!==', BookingStatus::Held);
        $upcoming = $todays->where('status', BookingStatus::Confirmed);
        $next = $upcoming->first(fn (Booking $booking) => $booking->starts_at->isAfter($now));
        $onNow = $upcoming->filter(fn (Booking $booking) => $booking->starts_at->lessThanOrEqualTo($now) && $booking->ends_at->isAfter($now));

        // What's still to be paid in the salon for today's appointments that haven't happened yet.
        $toTake = $upcoming
            ->filter(fn (Booking $booking) => $booking->ends_at->isAfter($now))
            ->sum(fn (Booking $booking) => max(0, $booking->price - (int) $booking->payments
                ->whereIn('status', [PaymentStatus::Captured, PaymentStatus::PartiallyRefunded])
                ->sum('amount')));

        $week = Booking::whereIn('status', [BookingStatus::Confirmed, BookingStatus::Completed, BookingStatus::NoShow])
            ->where('starts_at', '>=', $weekStart->utc())
            ->where('starts_at', '<', $weekEnd->utc())
            ->tap($mine)
            ->selectRaw('count(*) as bookings, coalesce(sum(price), 0) as value')
            ->first();

        // Appointments that have finished but nobody has said how they went.
        $waiting = Booking::where('status', BookingStatus::Confirmed)
            ->where('ends_at', '<', $now)
            ->where('starts_at', '>=', $dayStart->subDays(self::UNMARKED_DAYS)->utc())
            ->tap($mine);

        $unmarked = (clone $waiting)
            ->with(['customer', 'payments'])
            ->orderByDesc('starts_at')
            ->limit(8)
            ->get();

        $recent = Booking::with(['customer', 'payments'])
            ->where('source', 'online')
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Completed, BookingStatus::NoShow])
            ->tap($mine)
            ->latest('created_at')
            ->limit(5)
            ->get();

        $timeOff = TimeOff::where('starts_at', '<', $dayEnd->utc())
            ->where('ends_at', '>', $dayStart->utc())
            ->when(! $viewer->isOwner, fn ($query) => $query->where(fn ($query) => $query->whereNull('staff_id')->orWhereIn('staff_id', $viewer->staffIds)))
            ->orderBy('starts_at')
            ->get();

        $team = Staff::orderBy('position')->orderBy('id')->get(['id', 'name', 'is_active']);

        return Inertia::render('Dashboard', [
            'today' => $today,
            'timezone' => $zone,
            'isOwner' => $viewer->isOwner,
            'linked' => $viewer->isOwner || $viewer->staffIds !== [],
            'myStaffIds' => $viewer->staffIds,
            'bookingPageUrl' => route('booking.show', ['business' => $business->slug]),
            'team' => $team->map(fn (Staff $member) => ['id' => $member->id, 'name' => $member->name])->values(),
            'stats' => [
                'booked' => $booked->count(),
                'left' => $upcoming->filter(fn (Booking $booking) => $booking->ends_at->isAfter($now))->count(),
                'onNow' => $onNow->count(),
                'toTake' => $toTake,
                'weekBookings' => (int) ($week->bookings ?? 0),
                'weekValue' => (int) ($week->value ?? 0),
                'currency' => $business->currency,
            ],
            'next' => $next === null ? null : $viewer->booking($next),
            'bookings' => $todays->map(fn (Booking $booking) => $viewer->booking($booking))->values(),
            'timeOff' => $timeOff->map(fn (TimeOff $entry) => [
                'id' => $entry->id,
                'staff_id' => $entry->staff_id,
                'starts_at' => $entry->starts_at->utc()->toIso8601ZuluString(),
                'ends_at' => $entry->ends_at->utc()->toIso8601ZuluString(),
                'reason' => $entry->reason,
                'can_remove' => $viewer->canRemove($entry),
            ])->values(),
            'unmarked' => $unmarked->map(fn (Booking $booking) => $viewer->booking($booking))->values(),
            'unmarkedCount' => (clone $waiting)->count(),
            'recent' => $recent->map(fn (Booking $booking) => $viewer->booking($booking) + [
                'booked_at' => $booking->created_at?->utc()->toIso8601ZuluString(),
            ])->values(),
            'attention' => $viewer->isOwner ? $this->attention($business, $gateways) : null,
            'setup' => $viewer->isOwner ? $this->setup($business, $gateways) : null,
        ]);
    }

    /**
     * Things only an owner can sort out.
     *
     * @return array{failedRefunds: list<array{amount: int, currency: string, customer: string|null, gateway: string}>, blockedServices: list<string>}
     */
    private function attention(Business $business, Gateways $gateways): array
    {
        $failed = Refund::where('status', RefundStatus::Failed)->latest('id')->limit(5)->get();
        $payments = Payment::with('booking.customer')->whereIn('id', $failed->pluck('payment_id'))->get()->keyBy('id');

        return [
            'failedRefunds' => array_values($failed->map(fn (Refund $refund) => [
                'amount' => $refund->amount,
                'currency' => (string) $payments->get($refund->payment_id)?->currency,
                'customer' => $payments->get($refund->payment_id)?->booking?->customer?->name,
                'gateway' => (string) $payments->get($refund->payment_id)?->gateway,
            ])->all()),
            // Services that ask for a deposit can't be booked online with no way to pay it.
            'blockedServices' => $gateways->canTakeDeposits($business) ? [] : array_values(Service::where('is_active', true)
                ->orderBy('position')
                ->get()
                ->filter(fn (Service $service) => $service->depositAmount() > 0)
                ->map(fn (Service $service) => $service->name)
                ->all()),
        ];
    }

    /**
     * The steps to start taking bookings, until they're all done.
     *
     * @return array{services: bool, team: bool, hours: bool, assigned: bool, payments: bool, profile: bool}|null
     */
    private function setup(Business $business, Gateways $gateways): ?array
    {
        $steps = [
            'services' => Service::where('is_active', true)->exists(),
            'team' => Staff::where('is_active', true)->exists(),
            'hours' => Staff::where('is_active', true)->whereHas('workingHours')->exists(),
            'assigned' => Service::where('is_active', true)->whereHas('staff', fn ($query) => $query->where('staff.is_active', true))->exists(),
            'payments' => $gateways->canTakeDeposits($business),
            'profile' => $business->logo_path !== null || $business->description !== null,
        ];

        // Deposits and the profile are optional, so they don't hold the checklist open on their own.
        return $steps['services'] && $steps['team'] && $steps['hours'] && $steps['assigned'] ? null : $steps;
    }
}
