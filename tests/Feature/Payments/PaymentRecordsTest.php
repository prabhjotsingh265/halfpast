<?php

use App\Models\Booking;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Refund;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * The money rules are held by the database itself, so a bug in the app
 * can't record a deposit twice or refund more than was paid.
 */

function refusedByDatabase(callable $write): bool
{
    try {
        DB::transaction($write);
    } catch (QueryException) {
        return true;
    }

    return false;
}

beforeEach(function () {
    $this->payment = Payment::factory()->create(['amount' => 2000, 'currency' => 'GBP', 'captured_at' => now(), 'status' => 'captured']);
});

function ledgerEntry(Payment $payment, array $row): LedgerEntry
{
    $entry = new LedgerEntry;

    return $entry->forceFill(['business_id' => $payment->business_id, 'payment_id' => $payment->id, 'currency' => 'GBP', 'gateway' => 'test', ...$row]);
}

it('records a deposit in the ledger only once per payment', function () {
    expect(refusedByDatabase(fn () => ledgerEntry($this->payment, ['type' => 'deposit', 'amount' => 2000])->save()))->toBeFalse()
        ->and(refusedByDatabase(fn () => ledgerEntry($this->payment, ['type' => 'deposit', 'amount' => 2000])->save()))->toBeTrue();
});

it('keeps deposits positive and refunds negative and tied to a refund', function (array $row) {
    expect(refusedByDatabase(fn () => ledgerEntry($this->payment, $row)->save()))->toBeTrue();
})->with([
    'negative deposit' => [['type' => 'deposit', 'amount' => -2000]],
    'positive refund' => [['type' => 'refund', 'amount' => 500]],
    'refund without a refund record' => [['type' => 'refund', 'amount' => -500]],
]);

it('never refunds more than was paid', function () {
    expect(refusedByDatabase(fn () => $this->payment->update(['refunded_amount' => 2001])))->toBeTrue()
        ->and(refusedByDatabase(fn () => $this->payment->update(['refunded_amount' => 2000])))->toBeFalse();
});

it('refuses an empty refund', function () {
    $refund = new Refund;
    $refund->forceFill(['business_id' => $this->payment->business_id, 'payment_id' => $this->payment->id, 'amount' => 0, 'reason' => 'customer_cancelled']);

    expect(refusedByDatabase(fn () => $refund->save()))->toBeTrue();
});

it('stores each webhook once per gateway', function () {
    PaymentEvent::create(['gateway' => 'stripe', 'event_id' => 'evt_1', 'type' => 'checkout.session.completed', 'payload' => ['id' => 'evt_1']]);

    expect(refusedByDatabase(fn () => PaymentEvent::create(['gateway' => 'stripe', 'event_id' => 'evt_1', 'type' => 'checkout.session.completed', 'payload' => []])))->toBeTrue()
        ->and(refusedByDatabase(fn () => PaymentEvent::create(['gateway' => 'razorpay', 'event_id' => 'evt_1', 'type' => 'payment.captured', 'payload' => []])))->toBeFalse();
});

it('lists a booking\'s payments', function () {
    expect(Booking::withoutGlobalScopes()->find($this->payment->booking_id)->payments()->withoutGlobalScopes()->pluck('id')->all())->toBe([$this->payment->id]);
});
