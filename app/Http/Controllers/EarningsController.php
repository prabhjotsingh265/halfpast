<?php

namespace App\Http\Controllers;

use App\Enums\RefundStatus;
use App\Models\LedgerEntry;
use App\Models\Refund;
use App\Payments\Gateways;
use App\Tenancy\CurrentBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Deposits taken, refunds and the balance, from the ledger. Days are the
 * business's own calendar days, in its time zone.
 */
class EarningsController extends Controller
{
    /** @var array<string, string> */
    public const PERIODS = [
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        '90d' => 'Last 90 days',
        'this_month' => 'This month',
        'last_month' => 'Last month',
    ];

    public function index(Request $request, CurrentBusiness $current, Gateways $gateways): Response
    {
        $business = $current->require();
        $zone = $business->timezone;
        $period = array_key_exists((string) $request->query('period'), self::PERIODS) ? (string) $request->query('period') : '30d';
        [$from, $to] = self::range($period, CarbonImmutable::now($zone));

        $inPeriod = LedgerEntry::where('created_at', '>=', $from->utc())->where('created_at', '<', $to->utc());

        $totals = (clone $inPeriod)
            ->selectRaw("coalesce(sum(case when type = 'deposit' then amount end), 0) as taken")
            ->selectRaw("coalesce(-sum(case when type = 'refund' then amount end), 0) as refunded")
            ->first();

        $byDay = (clone $inPeriod)
            ->selectRaw('(created_at at time zone ?)::date as day', [$zone])
            ->selectRaw("coalesce(sum(case when type = 'deposit' then amount end), 0) as taken")
            ->selectRaw("coalesce(-sum(case when type = 'refund' then amount end), 0) as refunded")
            ->groupBy('day')
            ->get()
            ->keyBy(fn ($row) => (string) $row->getAttribute('day'));

        $days = [];
        for ($day = $from; $day->lessThan($to); $day = $day->addDay()) {
            $row = $byDay->get($day->toDateString());
            $days[] = [
                'date' => $day->toDateString(),
                'taken' => (int) ($row?->getAttribute('taken') ?? 0),
                'refunded' => (int) ($row?->getAttribute('refunded') ?? 0),
            ];
        }

        $labels = array_map(fn ($gateway) => $gateway->label(), $gateways->all());
        $byGateway = (clone $inPeriod)
            ->select('gateway')
            ->selectRaw("coalesce(sum(case when type = 'deposit' then amount end), 0) as taken")
            ->selectRaw("coalesce(-sum(case when type = 'refund' then amount end), 0) as refunded")
            ->groupBy('gateway')
            ->orderBy('gateway')
            ->get()
            ->map(fn ($row) => [
                'gateway' => $row->gateway,
                'label' => $labels[$row->gateway] ?? $row->gateway,
                'taken' => (int) $row->getAttribute('taken'),
                'refunded' => (int) $row->getAttribute('refunded'),
            ]);

        $recent = (clone $inPeriod)
            ->with(['payment.booking.customer'])
            ->latest('id')
            ->limit(25)
            ->get()
            ->map(fn (LedgerEntry $entry) => [
                'id' => $entry->id,
                'at' => $entry->created_at?->utc()->toIso8601ZuluString(),
                'type' => $entry->type,
                'amount' => $entry->amount,
                'gateway' => $labels[$entry->gateway] ?? $entry->gateway,
                'service' => $entry->payment?->booking?->service_name,
                'customer' => $entry->payment?->booking?->customer?->name,
                'reference' => $entry->payment?->booking?->displayCode(),
            ]);

        return Inertia::render('earnings/Index', [
            'currency' => $business->currency,
            'period' => $period,
            'periods' => collect(self::PERIODS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'totals' => [
                'taken' => (int) ($totals?->getAttribute('taken') ?? 0),
                'refunded' => (int) ($totals?->getAttribute('refunded') ?? 0),
                // Everything ever taken, less everything given back.
                'balance' => (int) LedgerEntry::sum('amount'),
            ],
            'refunds' => [
                'pending' => (int) Refund::where('status', RefundStatus::Pending)->sum('amount'),
                'pendingCount' => Refund::where('status', RefundStatus::Pending)->count(),
                'failedCount' => Refund::where('status', RefundStatus::Failed)->count(),
            ],
            'days' => $days,
            'byGateway' => $byGateway,
            'recent' => $recent,
        ]);
    }

    /**
     * The start and (exclusive) end of a period, as local midnights.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public static function range(string $period, CarbonImmutable $now): array
    {
        $tomorrow = $now->startOfDay()->addDay();

        return match ($period) {
            '7d' => [$tomorrow->subDays(7), $tomorrow],
            '90d' => [$tomorrow->subDays(90), $tomorrow],
            'this_month' => [$now->startOfMonth(), $tomorrow],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->startOfMonth()],
            default => [$tomorrow->subDays(30), $tomorrow],
        };
    }
}
