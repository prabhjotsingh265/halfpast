<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentOutcome;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Jobs\FinishPayment;
use App\Jobs\SendRefund;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Service;
use App\Models\Staff;
use App\Models\WorkingHour;
use App\Payments\CheckoutLinks;
use App\Payments\Gateways\TestGateway;
use App\Tenancy\CurrentBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00', 'UTC'));
    $this->business = Business::factory()->create(['slug' => 'glow-studio', 'payment_gateways' => ['test'], 'min_notice_minutes' => 60, 'slot_step_minutes' => 60]);
    app(CurrentBusiness::class)->set($this->business);
    $this->gateway = app(TestGateway::class);
});

/** A held booking with a checkout started at the test gateway. */
function bookingWithCheckout(array $booking = []): Payment
{
    $business = test()->business;
    $held = Booking::factory()->for($business)->held()->create([
        'staff_id' => Staff::factory()->for($business)->create()->id,
        'customer_id' => Customer::factory()->for($business)->create()->id,
        'deposit' => 2000,
        'currency' => 'GBP',
        ...$booking,
    ]);
    $payment = Payment::factory()->create(['booking_id' => $held->id, 'business_id' => $business->id, 'amount' => 2000, 'currency' => 'GBP', 'gateway_order_id' => null]);
    app(TestGateway::class)->begin($payment, $held, new CheckoutLinks('https://halfpast.test/back', 'https://halfpast.test/cancel'));

    return $payment->fresh();
}

it('settles a payment whose webhook never came and whose customer never came back (W9)', function () {
    $payment = bookingWithCheckout();
    $this->gateway->pay($payment->gateway_order_id);
    $this->travel(3)->minutes();

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Captured)
        ->and(Booking::find($payment->booking_id)->status)->toBe(BookingStatus::Confirmed);
});

it('leaves a fresh checkout alone, so the customer can come back by themselves', function () {
    $payment = bookingWithCheckout();
    $this->gateway->pay($payment->gateway_order_id);

    $this->artisan('payments:reconcile');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->fresh()->checked_at)->toBeNull();
});

it('asks about the same payment at most every couple of minutes', function () {
    $payment = bookingWithCheckout();
    $this->travel(3)->minutes();

    $this->artisan('payments:reconcile');
    $firstCheck = $payment->fresh()->checked_at;
    $this->travel(1)->minutes();
    $this->artisan('payments:reconcile');

    expect($payment->fresh()->checked_at->equalTo($firstCheck))->toBeTrue();
});

it('gives up on a checkout left unpaid for a day', function () {
    $payment = bookingWithCheckout();
    $this->travel(25)->hours();

    $this->artisan('payments:reconcile');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->fresh()->failure_reason)->toBe('abandoned')
        ->and($this->gateway->load($payment->gateway_order_id)['state'])->toBe('cancelled');
});

it('restarts a capture or refund whose retry job went missing', function () {
    Queue::fake([FinishPayment::class, SendRefund::class]);
    $stuck = bookingWithCheckout();
    $stuck->update(['status' => PaymentStatus::Authorized, 'outcome' => PaymentOutcome::Confirmed]);
    $refund = new Refund(['payment_id' => $stuck->id, 'amount' => 500, 'status' => RefundStatus::Pending, 'reason' => 'customer_cancelled']);
    $refund->business_id = $this->business->id;
    $refund->save();
    $this->travel(31)->minutes();

    $this->artisan('payments:reconcile');

    Queue::assertPushed(FinishPayment::class, fn ($job) => $job->paymentId === $stuck->id);
    Queue::assertPushed(SendRefund::class, fn ($job) => $job->refundId === $refund->id);
});

it('runs every minute', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'payments:reconcile'));

    expect($event?->expression)->toBe('* * * * *');
});

it('stops a lapsed customer\'s checkout when someone else takes the time', function () {
    $service = Service::factory()->for($this->business)->create(['duration_minutes' => 60]);
    $priya = Staff::factory()->for($this->business)->create();
    $service->staff()->attach($priya);
    $hour = new WorkingHour(['staff_id' => $priya->id, 'weekday' => 2, 'starts_at' => '09:00', 'ends_at' => '12:00']);
    $hour->business_id = $this->business->id;
    $hour->save();

    // The first customer's hold has lapsed with their checkout still open.
    $first = bookingWithCheckout([
        'staff_id' => $priya->id,
        'starts_at' => '2026-10-27 10:00:00',
        'ends_at' => '2026-10-27 11:00:00',
        'blocked_until' => '2026-10-27 11:00:00',
        'hold_expires_at' => now()->subMinute(),
    ]);
    // Another lapsed hold, at a different time, is left alone.
    $elsewhere = bookingWithCheckout([
        'staff_id' => $priya->id,
        'starts_at' => '2026-10-27 09:00:00',
        'ends_at' => '2026-10-27 10:00:00',
        'blocked_until' => '2026-10-27 10:00:00',
        'hold_expires_at' => now()->subMinute(),
    ]);

    $this->post('/glow-studio/hold', ['service_id' => $service->id, 'staff_id' => $priya->id, 'starts_at' => '2026-10-27T10:00:00Z'])
        ->assertSessionHasNoErrors();

    expect($this->gateway->load($first->gateway_order_id)['state'])->toBe('cancelled')
        ->and($this->gateway->load($elsewhere->gateway_order_id)['state'])->toBe('pending');

    // Paying now is impossible; if it had slipped through, settling would release it.
    $this->gateway->pay($first->gateway_order_id);
    expect($this->gateway->load($first->gateway_order_id)['state'])->toBe('cancelled');
});
