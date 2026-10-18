<?php

namespace App\Http\Controllers;

use App\Actions\Booking\FindBookableTimes;
use App\Availability\Slot;
use App\Models\Business;
use App\Models\Service;
use App\Models\Staff;
use App\Payments\Gateways;
use App\Support\Seo\BookingPageSeo;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The page customers book on. The choices so far (service, person, the
 * fortnight being looked at and the day picked) live in the query string,
 * so the page can be refreshed or shared, and each change only reloads the
 * days and times.
 */
class BookingPageController extends Controller
{
    /** How many days the strip of dates shows at once. */
    public const DAYS_SHOWN = 14;

    public function __construct(private FindBookableTimes $times, private Gateways $gateways) {}

    public function show(Request $request, Business $business): Response
    {
        $zone = $business->timezone;
        $today = CarbonImmutable::now($zone)->toDateString();
        $lastDay = CarbonImmutable::now($zone)->addDays($business->booking_horizon_days)->toDateString();

        $services = $this->bookableServices($business);
        $service = $services->firstWhere('id', $request->integer('service'));
        $staff = $service?->staff->firstWhere('id', $request->integer('staff'));

        $from = self::dateOr($request->query('from'), $today);
        $from = max($today, min($from, $lastDay));
        $to = CarbonImmutable::parse($from)->addDays(self::DAYS_SHOWN - 1)->toDateString();

        // Worked out once, and only when the page actually needs times.
        $slots = null;
        $window = function () use (&$slots, $business, $service, $staff, $from, $to): array {
            return $slots ??= $service === null ? [] : $this->times->handle($business, $service, $staff, $from, $to);
        };

        $byDate = fn (): Collection => collect($window())
            ->groupBy(fn (Slot $slot) => CarbonImmutable::instance($slot->start)->setTimezone($zone)->toDateString());

        $date = self::dateOr($request->query('date'), null);

        return Inertia::render('booking/Show', [
            // For search engines and link previews; only on a full page load.
            'seo' => fn () => BookingPageSeo::for($business, $services),
            'services' => $services->map(fn (Service $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'description' => $s->description,
                'duration_minutes' => $s->duration_minutes,
                'price' => $s->price,
                'deposit' => $s->depositAmount(),
                'staff_ids' => $s->staff->pluck('id')->values(),
            ])->values(),
            'staff' => $services->flatMap->staff->unique('id')->sortBy('position')->map(fn (Staff $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'bio' => $member->bio,
                'photo_url' => $member->photoUrl(),
            ])->values(),
            'today' => $today,
            'lastDay' => $lastDay,
            'holdMinutes' => $business->hold_minutes,
            'selection' => fn () => [
                'service_id' => $service?->id,
                'staff_id' => $staff?->id,
                'from' => $from,
                // With nothing picked yet, the first day that has a free time.
                'date' => $service === null ? null : ($date ?? $byDate()->keys()->sort()->first()),
            ],
            'days' => fn () => $service === null ? null : collect(range(0, self::DAYS_SHOWN - 1))
                ->map(fn (int $offset) => CarbonImmutable::parse($from)->addDays($offset)->toDateString())
                ->map(fn (string $day) => ['date' => $day, 'free' => $byDate()->get($day, collect())->count()])
                ->values(),
            'times' => function () use ($service, $date, $byDate, $business, $staff) {
                if ($service === null) {
                    return null;
                }

                $first = $byDate()->keys()->sort()->first();
                $day = $date ?? ($first === null ? null : (string) $first);

                if ($day === null) {
                    return [];
                }

                $slots = $byDate()->has($day)
                    ? $byDate()->get($day)
                    // A day outside the fortnight on show, from a shared link.
                    : collect($this->times->handle($business, $service, $staff, $day, $day));

                return $slots->map(fn (Slot $slot) => [
                    'starts_at' => CarbonImmutable::instance($slot->start)->utc()->toIso8601ZuluString(),
                    'staff_ids' => $slot->staffIds,
                ])->values();
            },
        ]);
    }

    /**
     * Services someone can actually be booked for: switched on, with at
     * least one person taking bookings who does them, and (if they take a
     * deposit) a way for the customer to pay it.
     *
     * @return Collection<int, Service>
     */
    private function bookableServices(Business $business): Collection
    {
        $canTakeDeposits = $this->gateways->canTakeDeposits($business);

        return Service::where('is_active', true)
            ->with(['staff' => fn ($query) => $query->where('staff.is_active', true)->orderBy('staff.position')->orderBy('staff.id')])
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->filter(fn (Service $service) => $service->staff->isNotEmpty())
            ->filter(fn (Service $service) => $canTakeDeposits || $service->depositAmount() === 0)
            ->values();
    }

    private static function dateOr(mixed $value, ?string $default): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))
            ? $value
            : $default;
    }
}
