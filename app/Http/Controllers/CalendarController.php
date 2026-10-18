<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Staff;
use App\Models\TimeOff;
use App\Models\WorkingHour;
use App\Tenancy\CurrentBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The owner's calendar: a day at a time, a column per person, with their
 * working hours, bookings and time off, in the business's own time.
 */
class CalendarController extends Controller
{
    public function index(Request $request, CurrentBusiness $current): Response
    {
        $business = $current->require();
        $zone = $business->timezone;
        $today = CarbonImmutable::now($zone)->toDateString();
        $date = self::date($request->query('date')) ?? $today;

        $dayStart = CarbonImmutable::parse($date, $zone)->startOfDay();
        $dayEnd = $dayStart->addDay()->startOfDay();

        $bookings = $this->bookingsBetween($dayStart, $dayEnd);

        // Everyone taking bookings, plus anyone else with a booking that day.
        $staff = Staff::where('is_active', true)
            ->orWhereIn('id', $bookings->pluck('staff_id')->filter())
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $hours = WorkingHour::whereIn('staff_id', $staff->pluck('id'))
            ->where('weekday', $dayStart->isoWeekday())
            ->orderBy('starts_at')
            ->get()
            ->groupBy('staff_id');

        $timeOff = TimeOff::where('starts_at', '<', $dayEnd->utc())
            ->where('ends_at', '>', $dayStart->utc())
            ->get();

        return Inertia::render('calendar/Index', [
            'date' => $date,
            'today' => $today,
            'timezone' => $zone,
            'staff' => $staff->map(fn (Staff $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'hours' => $hours->get($member->id, collect())->map(fn (WorkingHour $hour) => [
                    WorkingHour::minutesOf($hour->starts_at),
                    WorkingHour::minutesOf($hour->ends_at),
                ])->values(),
            ])->values(),
            'bookings' => $bookings->map(fn (Booking $booking) => self::present($booking))->values(),
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

    private static function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && strtotime($value) !== false ? $value : null;
    }
}
