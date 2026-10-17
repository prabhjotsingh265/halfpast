<?php

use App\Actions\Booking\CancelBooking;
use App\Actions\Payments\SettlePayment;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Events\BookingCancelled;
use App\Jobs\SendRefund;
use App\Listeners\RefundCancelledDeposit;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Staff;
use App\Payments\CheckoutLinks;
use App\Payments\Gateways\TestGateway;
use App\Tenancy\CurrentBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/*
 * A colour on Friday 30 October 2026 at 10:00 London time, with its £20
 * deposit paid through the test gateway. Free cancellation ends 24 hours before.
 */

beforeEach(function () {
    Mail::fake();
    $this->business = Business::factory()->create(['slug' => 'glow-studio', 'cancellation_window_hours' => 24, 'payment_gateways' => ['test']]);
    app(CurrentBusiness::class)->set($this->business);

    $this->booking = Booking::factory()->for($this->business)->held(60)
        ->at('2026-10-30 10:00', '2026-10-30 11:00')
        ->create([
            'staff_id' => Staff::factory()->for($this->business)->create()->id,
            'customer_id' => Customer::factory()->for($this->business)->create(['email' => 'ananya@example.com'])->id,
            'price' => 8000,
            'deposit' => 2000,
            'currency' => 'GBP',
        ]);

    // Paid and settled, the way checkout leaves it.
    $this->payment = Payment::factory()->create(['booking_id' => $this->booking->id, 'business_id' => $this->business->id, 'amount' => 2000, 'currency' => 'GBP', 'gateway_order_id' => null]);
    $this->gateway = app(TestGateway::class);
    $this->gateway->begin($this->payment, $this->booking, new CheckoutLinks('https://halfpast.test/back', 'https://halfpast.test/cancel'));
    $this->gateway->pay($this->payment->fresh()->gateway_order_id);
    app(SettlePayment::class)->handle($this->payment->fresh());
});

it('refunds the deposit when the customer cancels in time (C1)', function () {
    app(CancelBooking::class)->handle($this->booking, 'customer', now: CarbonImmutable::parse('2026-10-26 09:00', 'UTC'));

    $refund = Refund::sole();
    expect($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->reason)->toBe('customer_cancelled')
        ->and($refund->amount)->toBe(2000)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($this->gateway->load($this->payment->fresh()->gateway_order_id)['refunded'])->toBe(2000)
        ->and(LedgerEntry::orderBy('id')->pluck('amount')->all())->toBe([2000, -2000]);
});

it('keeps the deposit when the customer cancels inside the window (C2)', function () {
    app(CancelBooking::class)->handle($this->booking, 'customer', now: CarbonImmutable::parse('2026-10-30 08:00', 'UTC'));

    expect(Refund::count())->toBe(0)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Captured)
        ->and((int) LedgerEntry::sum('amount'))->toBe(2000);
});

it('refunds in full when the business cancels, whatever the timing (C4)', function () {
    app(CancelBooking::class)->handle($this->booking, 'business', refundInFull: true, now: CarbonImmutable::parse('2026-10-30 08:00', 'UTC'));

    expect(Refund::sole()->reason)->toBe('business_cancelled')
        ->and(Refund::sole()->status)->toBe(RefundStatus::Succeeded)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Refunded);
});

it('refunds once even if the cancellation is handled twice (C5)', function () {
    app(CancelBooking::class)->handle($this->booking, 'customer', now: CarbonImmutable::parse('2026-10-26 09:00', 'UTC'));

    app(RefundCancelledDeposit::class)->handle(new BookingCancelled($this->booking->fresh()));

    expect(Refund::count())->toBe(1)
        ->and($this->gateway->load($this->payment->fresh()->gateway_order_id)['refunded'])->toBe(2000)
        ->and(LedgerEntry::where('type', 'refund')->count())->toBe(1);
});

it('keeps trying a refund the gateway refused (C3)', function () {
    Queue::fake([SendRefund::class]);
    TestGateway::failNext('refund');

    app(CancelBooking::class)->handle($this->booking, 'customer', now: CarbonImmutable::parse('2026-10-26 09:00', 'UTC'));

    expect(Refund::sole()->status)->toBe(RefundStatus::Pending);
    Queue::assertPushed(SendRefund::class);

    app()->call([new SendRefund(Refund::sole()->id), 'handle']);

    expect(Refund::sole()->status)->toBe(RefundStatus::Succeeded)
        ->and($this->payment->fresh()->refunded_amount)->toBe(2000);
});

it('marks a refund that never goes through for someone to look at', function () {
    Queue::fake([SendRefund::class]);
    TestGateway::failNext('refund');
    app(CancelBooking::class)->handle($this->booking, 'customer', now: CarbonImmutable::parse('2026-10-26 09:00', 'UTC'));

    (new SendRefund(Refund::sole()->id))->failed(new RuntimeException('Still down.'));

    expect(Refund::sole()->status)->toBe(RefundStatus::Failed)
        ->and($this->payment->fresh()->refunded_amount)->toBe(0);
});

it('tells the customer once the refund has gone through', function () {
    app(CancelBooking::class)->handle($this->booking, 'customer', now: CarbonImmutable::parse('2026-10-26 09:00', 'UTC'));

    $this->get($this->booking->fresh()->manageUrl())
        ->assertInertia(fn ($page) => $page->where('booking.refund_due', 2000)->where('booking.refunded', true));
});
