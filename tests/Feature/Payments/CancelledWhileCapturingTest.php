<?php

use App\Actions\Booking\CancelBooking;
use App\Actions\Payments\SettlePayment;
use App\Enums\BookingStatus;
use App\Enums\PaymentOutcome;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Jobs\FinishPayment;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Staff;
use App\Payments\CheckoutLinks;
use App\Payments\GatewayError;
use App\Payments\Gateways\TestGateway;
use App\Tenancy\CurrentBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/*
 * The booking is confirmed but its £20 deposit hasn't been taken yet: the
 * gateway was down at capture, and a job is waiting to try again. Then the
 * booking is cancelled. Whatever the cancellation says about the deposit
 * must still hold when the job runs.
 *
 * A colour on Friday 30 October 2026 at 10:00 London time; free
 * cancellation ends 24 hours before.
 */

beforeEach(function () {
    Mail::fake();
    Queue::fake([FinishPayment::class]);
    $this->business = Business::factory()->create(['cancellation_window_hours' => 24, 'payment_gateways' => ['test']]);
    app(CurrentBusiness::class)->set($this->business);

    $this->booking = Booking::factory()->for($this->business)->held(60)
        ->at('2026-10-30 10:00', '2026-10-30 11:00')
        ->create([
            'staff_id' => Staff::factory()->for($this->business)->create()->id,
            'customer_id' => Customer::factory()->for($this->business)->create(['email' => 'ananya@example.com'])->id,
            'deposit' => 2000,
            'currency' => 'GBP',
        ]);

    $this->payment = Payment::factory()->create(['booking_id' => $this->booking->id, 'business_id' => $this->business->id, 'amount' => 2000, 'currency' => 'GBP', 'gateway_order_id' => null]);
    $this->gateway = app(TestGateway::class);
    $this->gateway->begin($this->payment, $this->booking, new CheckoutLinks('https://halfpast.test/back', 'https://halfpast.test/cancel'));
    $this->order = $this->payment->fresh()->gateway_order_id;
});

/** Paid, confirmed, and the capture failed: a retry is waiting. */
function captureWaiting(): void
{
    test()->gateway->pay(test()->order);
    TestGateway::failNext('capture');
    app(SettlePayment::class)->handle(test()->payment->fresh());

    expect(test()->booking->fresh()->status)->toBe(BookingStatus::Confirmed)
        ->and(test()->payment->fresh()->status)->toBe(PaymentStatus::Authorized);
    Queue::assertPushed(FinishPayment::class);
}

function runRetry(): void
{
    app()->call([new FinishPayment(test()->payment->id), 'handle']);
}

it('lets the authorisation go when the booking is cancelled in time', function () {
    captureWaiting();
    Exceptions::fake();

    app(CancelBooking::class)->handle($this->booking, 'customer', now: CarbonImmutable::parse('2026-10-26 09:00', 'UTC'));

    // Nothing to refund yet, and that's expected, not an error.
    Exceptions::assertNothingReported();
    expect(Refund::count())->toBe(0);

    runRetry();

    $payment = $this->payment->fresh();
    expect($payment->status)->toBe(PaymentStatus::Released)
        ->and($payment->release_reason)->toBe('booking_cancelled')
        ->and($payment->captured_at)->toBeNull()
        ->and($this->gateway->load($this->order)['state'])->toBe('released')
        ->and(LedgerEntry::count())->toBe(0)
        ->and(Refund::count())->toBe(0);

    Mail::assertQueued(BookingCancelledMail::class);
    Mail::assertNotQueued(BookingConfirmedMail::class);
    Mail::assertNotSent(BookingConfirmedMail::class);
});

it('takes the deposit the business keeps when the booking is cancelled late', function () {
    captureWaiting();

    app(CancelBooking::class)->handle($this->booking, 'customer', now: CarbonImmutable::parse('2026-10-30 08:00', 'UTC'));
    runRetry();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Captured)
        ->and($this->gateway->load($this->order)['state'])->toBe('captured')
        ->and(LedgerEntry::pluck('amount')->all())->toBe([2000])
        ->and(Refund::count())->toBe(0);

    // The booking is off, so no confirmation goes out.
    Mail::assertNotQueued(BookingConfirmedMail::class);
    Mail::assertNotSent(BookingConfirmedMail::class);
});

it('refunds straight away when the money arrives after the booking was cancelled', function () {
    // Taken at the gateway, but not yet recorded as taken when the cancellation lands.
    $this->gateway->pay($this->order, capture: true);
    $this->booking->update(['status' => BookingStatus::Confirmed, 'hold_expires_at' => null]);
    $this->payment->update([
        'outcome' => PaymentOutcome::Confirmed,
        'status' => PaymentStatus::Authorized,
        'captured_at' => now(),
        'gateway_payment_id' => $this->gateway->load($this->order)['payment_id'],
    ]);

    app(CancelBooking::class)->handle($this->booking, 'business', refundInFull: true, now: CarbonImmutable::parse('2026-10-30 08:00', 'UTC'));
    expect(Refund::count())->toBe(0);

    app(SettlePayment::class)->finish($this->payment->fresh());

    $refund = Refund::sole();
    expect($refund->reason)->toBe('business_cancelled')
        ->and($refund->amount)->toBe(2000)
        ->and($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and(LedgerEntry::orderBy('id')->pluck('amount')->all())->toBe([2000, -2000]);

    Mail::assertNotQueued(BookingConfirmedMail::class);
    Mail::assertNotSent(BookingConfirmedMail::class);
});

it('keeps trying to let the money go if the gateway is down', function () {
    captureWaiting();
    TestGateway::failNext('release');

    app(CancelBooking::class)->handle($this->booking, 'business', refundInFull: true, now: CarbonImmutable::parse('2026-10-26 09:00', 'UTC'));

    // The release failed, so the job will try again; nothing has moved.
    expect(fn () => runRetry())->toThrow(GatewayError::class);
    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Authorized);

    runRetry();
    runRetry();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Released)
        ->and(Refund::count())->toBe(0)
        ->and(LedgerEntry::count())->toBe(0);
});
