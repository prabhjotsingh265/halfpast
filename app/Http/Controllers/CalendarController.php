<?php

namespace App\Http\Controllers;

use App\Actions\Booking\CancellationTerms;
use App\Actions\Booking\RecordVisit;
use App\Enums\BookingStatus;
use App\Enums\BusinessRole;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Staff;
use App\Models\TimeOff;
use App\Models\WorkingHour;
use App\Tenancy\CurrentBusiness;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The calendar, in the business's own time: a day with a column per person,
 * a week (Monday to Sunday) with a column per day, or that week as a list.
 * It can be narrowed to one person, and remembers the last view used.
 */
class CalendarController extends Controller
{
    public const VIEWS = ['day', 'week', 'list'];

    public const SESSION_VIEW = 'calendar.view';

    public function index(Request $request, CurrentBusiness $current): Response
    {
        $business = $current->require();
        $zone = $business->timezone;
        $today = CarbonImmutable::now($zone)->toDateString();
        $date = self::date($request->query('date')) ?? $today;
        $view = $this->view($request);

        $from = CarbonImmutable::parse($date, $zone)->startOfDay();

        if ($view !== 'day') {
            $from = $from->startOfWeek(CarbonInterface::MONDAY);
        }

        // Whole calendar days, so a week with a clock change still ends at midnight.
        $length = $view === 'day' ? 1 : 7;
        $to = $from->addDays($length)->startOfDay();
        $days = collect(range(0, $length - 1))->map(fn (int $i) => $from->addDays($i)->toDateString());

        $person = self::id($request->query('staff'));
        $person = $person === null ? null : Staff::find($person);

        $bookings = $this->bookingsBetween($from, $to)
            ->when($person !== null, fn (Collection $all) => $all->where('staff_id', $person?->id)->values());

        $team = Staff::where('is_active', true)->orderBy('position')->orderBy('id')->get();

        // One person, or everyone taking bookings plus anyone else booked in this range.
        $staff = $person !== null ? collect([$person]) : Staff::where('is_active', true)
            ->orWhereIn('id', $bookings->pluck('staff_id')->filter())
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        // Each person's working hours by weekday, in minutes since midnight.
        /** @var array<int, array<int, list<array{int, int}>>> $weekly */
        $weekly = [];

        foreach (WorkingHour::whereIn('staff_id', $staff->pluck('id'))->orderBy('starts_at')->get() as $hour) {
            $weekly[$hour->staff_id][$hour->weekday][] = [
                WorkingHour::minutesOf($hour->starts_at),
                WorkingHour::minutesOf($hour->ends_at),
            ];
        }

        // What this person can do with each booking: the rules in BookingPolicy,
        // worked out once for the whole calendar rather than per booking.
        $user = $request->user();
        $owner = $user !== null && $user->roleIn($business) === BusinessRole::Owner;
        $mine = $owner || $user === null ? [] : Staff::where('user_id', $user->id)->pluck('id')->all();

        $timeOff = TimeOff::where('starts_at', '<', $to->utc())
            ->where('ends_at', '>', $from->utc())
            ->when($person !== null, fn ($query) => $query->where(fn ($query) => $query->whereNull('staff_id')->orWhere('staff_id', $person?->id)))
            ->orderBy('starts_at')
            ->get();

        // For adding a booking by hand: what's on offer, and who this person
        // can book in (anyone for an owner; staff, themselves).
        $services = Service::where('is_active', true)
            ->with(['staff' => fn ($query) => $query->where('staff.is_active', true)->select('staff.id')])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return Inertia::render('calendar/Index', [
            'view' => $view,
            'date' => $date,
            'days' => $days->values(),
            'today' => $today,
            'timezone' => $zone,
            'person' => $person?->id,
            'team' => $team->concat($person !== null && ! $team->contains($person) ? [$person] : [])
                ->map(fn (Staff $member) => ['id' => $member->id, 'name' => $member->name])
                ->values(),
            'newBooking' => [
                'services' => $services->map(fn (Service $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'duration' => $service->duration_minutes,
                    'price' => $service->price,
                    'staff_ids' => $service->staff->pluck('id')->values(),
                ])->values(),
                'staff' => ($owner ? $team : $team->whereIn('id', $mine))
                    ->map(fn (Staff $member) => ['id' => $member->id, 'name' => $member->name])
                    ->values(),
                'currency' => $business->currency,
            ],
            'staff' => $staff->map(fn (Staff $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'hours' => $days->mapWithKeys(fn (string $day) => [
                    $day => $weekly[$member->id][CarbonImmutable::parse($day)->isoWeekday()] ?? [],
                ]),
            ])->values(),
            'bookings' => $bookings->map(fn (Booking $booking) => self::present($booking) + ['can' => [
                'mark' => ($owner || in_array($booking->staff_id, $mine, true)) && RecordVisit::allowed($booking),
                'cancel' => $owner && CancellationTerms::for($booking, $business)->allowed,
            ]])->values(),
            'timeOff' => $timeOff->map(fn (TimeOff $entry) => [
                'id' => $entry->id,
                'staff_id' => $entry->staff_id,
                'starts_at' => $entry->starts_at->utc()->toIso8601ZuluString(),
                'ends_at' => $entry->ends_at->utc()->toIso8601ZuluString(),
                'reason' => $entry->reason,
            ])->values(),
        ]);
    }

    /**
     * Bookings that show on a calendar between two moments: confirmed,
     * finished or missed ones, and holds still running.
     *
     * @return Collection<int, Booking>
     */
    public function bookingsBetween(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Booking::with(['customer', 'payments'])
            ->where('starts_at', '<', $to->utc())
            ->where('blocked_until', '>', $from->utc())
            ->where(fn ($query) => $query
                ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Completed, BookingStatus::NoShow])
                ->orWhere(fn ($query) => $query->where('status', BookingStatus::Held)->where('hold_expires_at', '>', now())))
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(Booking $booking): array
    {
        $paid = $booking->payments->first(fn (Payment $payment) => in_array($payment->status, [PaymentStatus::Captured, PaymentStatus::PartiallyRefunded], true));

        return [
            'id' => $booking->id,
            'code' => $booking->code,
            'reference' => $booking->displayCode(),
            'staff_id' => $booking->staff_id,
            'service_name' => $booking->service_name,
            'status' => $booking->status->value,
            'source' => $booking->source,
            'starts_at' => $booking->starts_at->utc()->toIso8601ZuluString(),
            'ends_at' => $booking->ends_at->utc()->toIso8601ZuluString(),
            'blocked_until' => $booking->blocked_until->utc()->toIso8601ZuluString(),
            'price' => $booking->price,
            'deposit' => $booking->deposit,
            'currency' => $booking->currency,
            'paid' => $paid === null ? null : ['amount' => $paid->amount, 'gateway' => $paid->gateway],
            'notes' => $booking->notes,
            'customer' => $booking->customer === null ? null : [
                'name' => $booking->customer->name,
                'email' => $booking->customer->email,
                'phone' => $booking->customer->phone,
            ],
        ];
    }

    /** The view asked for, kept for next time; otherwise the last one used. */
    private function view(Request $request): string
    {
        $asked = $request->query('view');

        if (is_string($asked) && in_array($asked, self::VIEWS, true)) {
            $request->session()->put(self::SESSION_VIEW, $asked);

            return $asked;
        }

        $remembered = $request->session()->get(self::SESSION_VIEW);

        return is_string($remembered) && in_array($remembered, self::VIEWS, true) ? $remembered : 'day';
    }

    private static function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && strtotime($value) !== false ? $value : null;
    }

    private static function id(mixed $value): ?int
    {
        return is_string($value) && ctype_digit($value) && strlen($value) < 10 ? (int) $value : null;
    }
}
