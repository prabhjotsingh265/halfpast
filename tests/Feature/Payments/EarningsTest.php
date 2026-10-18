<?php

use App\Enums\BusinessRole;
use App\Enums\RefundStatus;
use App\Http\Controllers\EarningsController;
use App\Models\Business;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Wednesday 21 October 2026, 12:00 London time (British Summer Time).
 */

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-21 11:00', 'UTC'));
    $this->owner = User::factory()->create();
    $this->business = Business::factory()->ownedBy($this->owner)->create(['timezone' => 'Europe/London', 'currency' => 'GBP']);
});

/** A ledger row at a given UTC moment. */
function ledger(Business $business, string $type, int $amount, string $at, string $gateway = 'stripe'): LedgerEntry
{
    $payment = Payment::factory()->create(['business_id' => $business->id, 'gateway' => $gateway, 'amount' => abs($amount), 'status' => 'captured']);
    $refundId = null;

    if ($type === 'refund') {
        $refund = new Refund(['payment_id' => $payment->id, 'amount' => abs($amount), 'status' => RefundStatus::Succeeded, 'reason' => 'customer_cancelled']);
        $refund->business_id = $business->id;
        $refund->save();
        $refundId = $refund->id;
    }

    $entry = new LedgerEntry;
    $entry->forceFill([
        'business_id' => $business->id,
        'payment_id' => $payment->id,
        'refund_id' => $refundId,
        'type' => $type,
        'amount' => $type === 'refund' ? -abs($amount) : abs($amount),
        'currency' => 'GBP',
        'gateway' => $gateway,
        'created_at' => CarbonImmutable::parse($at, 'UTC'),
    ])->save();

    return $entry;
}

it('adds up deposits, refunds and the balance for a period', function () {
    ledger($this->business, 'deposit', 2000, '2026-10-20 09:00');
    ledger($this->business, 'deposit', 3000, '2026-10-21 08:00', 'razorpay');
    ledger($this->business, 'refund', 2000, '2026-10-21 09:00');
    // Older than the last 7 days, but still part of the balance.
    ledger($this->business, 'deposit', 5000, '2026-09-01 10:00');

    $this->actingAs($this->owner)
        ->get(route('earnings.index', ['period' => '7d']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('earnings/Index')
            ->where('period', '7d')
            ->where('totals', ['taken' => 5000, 'refunded' => 2000, 'balance' => 8000])
            ->has('days', 7)
            ->where('days.6', ['date' => '2026-10-21', 'taken' => 3000, 'refunded' => 2000])
            ->where('days.5', ['date' => '2026-10-20', 'taken' => 2000, 'refunded' => 0])
            ->has('recent', 3));
});

it('counts days as the business sees them, in its own time zone', function () {
    // 23:30 on the 20th in London is 22:30 UTC: it belongs to the 20th, not the 21st.
    ledger($this->business, 'deposit', 1500, '2026-10-20 22:30');

    $this->actingAs($this->owner)
        ->get(route('earnings.index', ['period' => '7d']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.5', ['date' => '2026-10-20', 'taken' => 1500, 'refunded' => 0])
            ->where('days.6.taken', 0));
});

it('breaks the period down by payment method', function () {
    ledger($this->business, 'deposit', 2000, '2026-10-20 09:00', 'stripe');
    ledger($this->business, 'deposit', 3000, '2026-10-21 08:00', 'razorpay');
    ledger($this->business, 'refund', 3000, '2026-10-21 09:00', 'razorpay');

    $this->actingAs($this->owner)
        ->get(route('earnings.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('byGateway', [
                ['gateway' => 'razorpay', 'label' => 'UPI, cards and netbanking', 'taken' => 3000, 'refunded' => 3000],
                ['gateway' => 'stripe', 'label' => 'Card', 'taken' => 2000, 'refunded' => 0],
            ]));
});

it('shows refunds still on the way and any that need looking at', function () {
    $payment = Payment::factory()->create(['business_id' => $this->business->id, 'amount' => 2000, 'status' => 'captured']);

    foreach ([[RefundStatus::Pending, 700], [RefundStatus::Pending, 300], [RefundStatus::Failed, 500]] as [$status, $amount]) {
        $refund = new Refund(['payment_id' => $payment->id, 'amount' => $amount, 'status' => $status, 'reason' => 'customer_cancelled']);
        $refund->business_id = $this->business->id;
        $refund->save();
    }

    $this->actingAs($this->owner)
        ->get(route('earnings.index'))
        ->assertInertia(fn (Assert $page) => $page->where('refunds', ['pending' => 1000, 'pendingCount' => 2, 'failedCount' => 1]));
});

it("never counts another business's money", function () {
    ledger(Business::factory()->create(), 'deposit', 9999, '2026-10-21 08:00');
    ledger($this->business, 'deposit', 2000, '2026-10-21 08:00');

    $this->actingAs($this->owner)
        ->get(route('earnings.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('totals.balance', 2000)
            ->has('recent', 1));
});

it('works out each period as local days', function (string $period, string $from, string $to) {
    [$start, $end] = EarningsController::range($period, CarbonImmutable::parse('2026-10-21 12:00', 'Europe/London'));

    expect($start->toDateTimeString())->toBe($from)
        ->and($end->toDateTimeString())->toBe($to);
})->with([
    'last 7 days' => ['7d', '2026-10-15 00:00:00', '2026-10-22 00:00:00'],
    'this month' => ['this_month', '2026-10-01 00:00:00', '2026-10-22 00:00:00'],
    'last month' => ['last_month', '2026-09-01 00:00:00', '2026-10-01 00:00:00'],
]);

it('falls back to the last 30 days for an unknown period', function () {
    $this->actingAs($this->owner)
        ->get(route('earnings.index', ['period' => 'forever']))
        ->assertInertia(fn (Assert $page) => $page->where('period', '30d')->has('days', 30));
});

it('is only for owners', function () {
    $staff = User::factory()->create();
    $this->business->addMember($staff, BusinessRole::Staff);

    $this->actingAs($staff)->get(route('earnings.index'))->assertForbidden();
});
