<?php

use App\Actions\Payments\RefundPayment;
use App\Actions\Payments\SettlePayment;
use App\Enums\BookingStatus;
use App\Enums\PaymentOutcome;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Jobs\FinishPayment;
use App\Jobs\SendRefund;
use App\Mail\BookingConfirmedMail;
use App\Mail\PaymentReturnedMail;
use App\Models\Booking;
use App\Models\Business;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Service;
use App\Models\Staff;
use App\Models\WorkingHour;
use App\Payments\Gateways\TestGateway;
use App\Payments\GatewayState;
use App\Payments\GatewayStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The scenarios from the payments design note (H, P, W, L, T numbers).
 *
 * Monday 26 October 2026, 08:00 in London. A colour takes a £20 deposit on
 * an £80 price. Priya works weekday mornings. Holds last 15 minutes, paying
 * adds a fresh 15, and no hold lasts more than 45 in total.
 */

beforeEach(function () {
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00', 'UTC'));

    $this->business = Business::factory()->create([
        'slug' => 'glow-studio',
        'timezone' => 'Europe/London',
        'currency' => 'GBP',
        'slot_step_minutes' => 60,
        'min_notice_minutes' => 60,
        'hold_minutes' => 15,
        'payment_gateways' => ['test'],
    ]);
    $this->colour = Service::factory()->for($this->business)->withFixedDeposit(2000)
        ->create(['name' => 'Colour', 'duration_minutes' => 60, 'price' => 8000]);
    $this->priya = Staff::factory()->for($this->business)->create(['name' => 'Priya']);
    $this->colour->staff()->attach($this->priya);

    foreach (range(1, 5) as $weekday) {
        $hour = new WorkingHour(['staff_id' => $this->priya->id, 'weekday' => $weekday, 'starts_at' => '09:00', 'ends_at' => '12:00']);
        $hour->business_id = $this->business->id;
        $hour->save();
    }

    $this->gateway = app(TestGateway::class);
});

/** Hold Tuesday 10:00 and fill in the details, as a customer would. */
function heldWithDetails(string $startsAt = '2026-10-27T10:00:00Z'): Booking
{
    test()->post('/glow-studio/hold', ['service_id' => test()->colour->id, 'starts_at' => $startsAt]);
    $booking = Booking::where('status', BookingStatus::Held)->latest('id')->firstOrFail();

    test()->post("/glow-studio/checkout/{$booking->code}", ['name' => 'Ananya Iyer', 'email' => 'ananya@example.com']);

    return $booking->fresh();
}

function startPaying(Booking $booking, string $gateway = 'test'): TestResponse
{
    return test()->post("/glow-studio/checkout/{$booking->code}/pay", ['gateway' => $gateway]);
}

function latestPayment(): Payment
{
    return Payment::latest('id')->firstOrFail();
}

function comeBack(Payment $payment): TestResponse
{
    $booking = Booking::findOrFail($payment->booking_id);

    return test()->get("/glow-studio/checkout/{$booking->code}/payments/{$payment->id}");
}

/** Someone else confirms the same time with Priya. */
function someoneElseBooks(Booking $booking): void
{
    Booking::factory()->for(test()->business)
        ->at($booking->starts_at->toDateTimeString(), $booking->ends_at->toDateTimeString())
        ->create(['staff_id' => $booking->staff_id]);
}

describe('starting a payment', function () {
    it('sends the customer to the gateway with a fresh 15 minutes on their hold', function () {
        $booking = heldWithDetails();
        $this->travel(5)->minutes();

        $response = startPaying($booking);

        $payment = latestPayment();
        $response->assertRedirect();
        expect($response->headers->get('Location'))->toContain('/payments/test/'.$payment->gateway_order_id)
            ->and($payment->status)->toBe(PaymentStatus::Pending)
            ->and($payment->amount)->toBe(2000)
            ->and($payment->currency)->toBe('GBP')
            ->and($booking->fresh()->hold_expires_at->toIso8601ZuluString())->toBe('2026-10-26T08:20:00Z');
    });

    it('never holds a time for more than 45 minutes in all (H6)', function () {
        $booking = heldWithDetails();

        $this->travel(40)->minutes();
        startPaying($booking);
        expect($booking->fresh()->hold_expires_at->toIso8601ZuluString())->toBe('2026-10-26T08:45:00Z');

        $this->travel(6)->minutes();
        startPaying($booking)->assertSessionHasErrors('payment');

        expect(session('errors')->first('payment'))->toContain('held for a while');
    });

    it('holds the time again if the hold was cleared but the time is free (H4)', function () {
        $booking = heldWithDetails();
        $this->travel(20)->minutes();
        $this->artisan('bookings:expire-holds');

        startPaying($booking);

        $renewed = Booking::where('status', BookingStatus::Held)->sole();
        expect($renewed->id)->not->toBe($booking->id)
            ->and($renewed->customer_id)->toBe($booking->fresh()->customer_id)
            ->and(latestPayment()->booking_id)->toBe($renewed->id);
    });

    it("doesn't start a payment if the cleared time was taken (H5)", function () {
        $booking = heldWithDetails();
        $this->travel(20)->minutes();
        $this->artisan('bookings:expire-holds');
        someoneElseBooks($booking);

        startPaying($booking)->assertSessionHasErrors('payment');

        expect(Payment::count())->toBe(0);
    });

    it('replaces an unfinished attempt with a new one (P3)', function () {
        $booking = heldWithDetails();
        startPaying($booking);
        $first = latestPayment();

        startPaying($booking);

        expect($first->fresh()->status)->toBe(PaymentStatus::Failed)
            ->and($first->fresh()->failure_reason)->toBe('replaced')
            ->and($this->gateway->load($first->gateway_order_id)['state'])->toBe('cancelled')
            ->and(Payment::where('status', PaymentStatus::Pending)->count())->toBe(1);
    });

    it('says so, and keeps the hold, when the gateway is down (P4)', function () {
        $booking = heldWithDetails();
        TestGateway::failNext('begin');

        startPaying($booking)->assertSessionHasErrors('payment');

        expect(latestPayment()->status)->toBe(PaymentStatus::Failed)
            ->and(latestPayment()->failure_reason)->toBe('could_not_start')
            ->and($booking->fresh()->status)->toBe(BookingStatus::Held);
    });

    it('only uses a gateway the business offers (P1, P2)', function () {
        $booking = heldWithDetails();

        startPaying($booking, 'stripe')->assertSessionHasErrors('payment');

        expect(Payment::count())->toBe(0);
    });

    it('wants the details before payment', function () {
        $this->post('/glow-studio/hold', ['service_id' => $this->colour->id, 'starts_at' => '2026-10-27T10:00:00Z']);
        $booking = Booking::sole();

        startPaying($booking)->assertSessionHasErrors('payment');
    });
});

describe('coming back from the gateway', function () {
    it('confirms the booking, takes the money and sends the confirmation (W1)', function () {
        $booking = heldWithDetails();
        startPaying($booking);
        $payment = latestPayment();
        $this->gateway->pay($payment->gateway_order_id);

        comeBack($payment)->assertRedirect($booking->fresh()->manageUrl());

        expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed)
            ->and($payment->fresh()->status)->toBe(PaymentStatus::Captured)
            ->and($payment->fresh()->outcome)->toBe(PaymentOutcome::Confirmed)
            ->and($this->gateway->load($payment->gateway_order_id)['state'])->toBe('captured')
            ->and(LedgerEntry::sole()->amount)->toBe(2000);

        Mail::assertQueued(BookingConfirmedMail::class, 1);
    });

    it('settles only once, however often it is asked (W3, W6)', function () {
        $booking = heldWithDetails();
        startPaying($booking);
        $payment = latestPayment();
        $this->gateway->pay($payment->gateway_order_id);

        comeBack($payment);
        comeBack($payment);
        app(SettlePayment::class)->handle($payment->fresh());

        expect(LedgerEntry::count())->toBe(1);
        Mail::assertQueued(BookingConfirmedMail::class, 1);
    });

    it('keeps the hold when the customer comes back without paying (W7)', function () {
        $booking = heldWithDetails();
        $redirect = startPaying($booking)->headers->get('Location');

        $this->post($redirect, ['action' => 'cancel'])->assertRedirect("/glow-studio/checkout/{$booking->code}?cancelled=1");

        $this->get("/glow-studio/checkout/{$booking->code}?cancelled=1")
            ->assertInertia(fn (Assert $page) => $page->where('cancelled', true)->where('booking.held', true));
    });

    it('lets the customer try again after a declined card (W8)', function () {
        $booking = heldWithDetails();
        startPaying($booking);
        $payment = latestPayment();
        $this->gateway->decline($payment->gateway_order_id);

        comeBack($payment)->assertRedirect("/glow-studio/checkout/{$booking->code}?returned=1");

        expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
            ->and($payment->fresh()->failure_reason)->toBe('declined')
            ->and($booking->fresh()->status)->toBe(BookingStatus::Held);

        startPaying($booking);
        $this->gateway->pay(latestPayment()->gateway_order_id);
        comeBack(latestPayment());

        expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
    });

    it('waits when the gateway has not finished yet', function () {
        $booking = heldWithDetails();
        startPaying($booking);

        comeBack(latestPayment())->assertRedirect("/glow-studio/checkout/{$booking->code}?returned=1");

        $this->get("/glow-studio/checkout/{$booking->code}?returned=1")
            ->assertInertia(fn (Assert $page) => $page->where('payment.status', 'pending')->where('returned', true));
    });

    it('settles for a customer who comes back in another browser', function () {
        $booking = heldWithDetails();
        startPaying($booking);
        $payment = latestPayment();
        $this->gateway->pay($payment->gateway_order_id);
        $this->flushSession();

        comeBack($payment)->assertRedirect($booking->fresh()->manageUrl());
    });

    it("won't settle another booking's payment through this one's address (T2)", function () {
        $booking = heldWithDetails();
        startPaying($booking);
        $payment = latestPayment();
        $other = Booking::factory()->for($this->business)->held()->at('2026-10-28 10:00', '2026-10-28 11:00')->create(['staff_id' => $this->priya->id]);

        $this->get("/glow-studio/checkout/{$other->code}/payments/{$payment->id}")->assertNotFound();
    });
});

describe('paying after the hold ran out', function () {
    it('confirms when the hold lapsed but nobody took the time (L1)', function () {
        $booking = heldWithDetails();
        startPaying($booking);
        $payment = latestPayment();
        $this->travel(30)->minutes();
        $this->gateway->pay($payment->gateway_order_id);

        comeBack($payment);

        expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed)
            ->and($payment->fresh()->status)->toBe(PaymentStatus::Captured);
    });

    it('brings the booking back when the hold was cleared and the time is free (L2)', function () {
        $booking = heldWithDetails();
        $redirect = startPaying($booking)->headers->get('Location');

        $this->post($redirect, ['action' => 'pay_late']);
        comeBack(latestPayment());

        expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed)
            ->and(latestPayment()->status)->toBe(PaymentStatus::Captured);
    });

    it('releases the money, never charging, when someone else took the time (L3)', function () {
        $booking = heldWithDetails();
        $redirect = startPaying($booking)->headers->get('Location');
        $payment = latestPayment();

        $this->post($redirect, ['action' => 'pay_after_taken']);
        comeBack($payment)->assertRedirect("/glow-studio/checkout/{$booking->code}?returned=1");

        expect($payment->fresh()->status)->toBe(PaymentStatus::Released)
            ->and($payment->fresh()->outcome)->toBe(PaymentOutcome::SlotTaken)
            ->and($payment->fresh()->captured_at)->toBeNull()
            ->and($this->gateway->load($payment->gateway_order_id)['state'])->toBe('released')
            ->and($booking->fresh()->status)->toBe(BookingStatus::Expired)
            ->and(LedgerEntry::count())->toBe(0);

        Mail::assertQueued(PaymentReturnedMail::class, fn ($mail) => $mail->hasTo('ananya@example.com'));
        Mail::assertNotQueued(BookingConfirmedMail::class);

        $this->get("/glow-studio/checkout/{$booking->code}?returned=1")
            ->assertInertia(fn (Assert $page) => $page
                ->where('payment.outcome', 'slot_taken')
                ->where('payment.refunded', false)
                ->where('booking.renewable', false));
    });

    it('refunds in full when the money was already taken and the time is gone (L4)', function () {
        $booking = heldWithDetails();
        startPaying($booking);
        $payment = latestPayment();
        $booking->update(['status' => BookingStatus::Expired]);
        someoneElseBooks($booking);
        $this->gateway->pay($payment->gateway_order_id, capture: true);

        comeBack($payment);

        $payment->refresh();
        expect($payment->status)->toBe(PaymentStatus::Refunded)
            ->and($payment->refunded_amount)->toBe(2000)
            ->and(Refund::sole()->status)->toBe(RefundStatus::Succeeded)
            ->and(Refund::sole()->reason)->toBe('slot_taken')
            ->and($this->gateway->load($payment->gateway_order_id)['refunded'])->toBe(2000)
            ->and(LedgerEntry::orderBy('id')->pluck('amount')->all())->toBe([2000, -2000])
            ->and((int) LedgerEntry::sum('amount'))->toBe(0);

        Mail::assertQueued(PaymentReturnedMail::class, 1);
    });

    it('races fairly: a late payment and a new hold for the same time, exactly one wins (X1)', function () {
        $booking = heldWithDetails();
        startPaying($booking);
        $payment = latestPayment();
        $booking->update(['status' => BookingStatus::Expired]);

        // A new customer holds the same time first.
        $this->flushSession();
        $this->post('/glow-studio/hold', ['service_id' => $this->colour->id, 'starts_at' => '2026-10-27T10:00:00Z']);
        $this->gateway->pay($payment->gateway_order_id);

        app(SettlePayment::class)->handle($payment->fresh());

        expect($payment->fresh()->outcome)->toBe(PaymentOutcome::SlotTaken)
            ->and(Booking::whereIn('status', [BookingStatus::Held, BookingStatus::Confirmed])->count())->toBe(1);
    });
});

describe('when the gateway fails part-way', function () {
    it('keeps the booking and takes the money on a retry (L5)', function () {
        Queue::fake([FinishPayment::class]);
        $booking = heldWithDetails();
        startPaying($booking);
        $payment = latestPayment();
        $this->gateway->pay($payment->gateway_order_id);
        TestGateway::failNext('capture');

        comeBack($payment)->assertRedirect($booking->fresh()->manageUrl());

        expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed)
            ->and($payment->fresh()->status)->toBe(PaymentStatus::Authorized);
        Queue::assertPushed(FinishPayment::class);
        Mail::assertNotQueued(BookingConfirmedMail::class);

        app()->call([new FinishPayment($payment->id), 'handle']);

        expect($payment->fresh()->status)->toBe(PaymentStatus::Captured);
        Mail::assertQueued(BookingConfirmedMail::class, 1);
    });

    it('cancels the booking if the money still cannot be taken after every retry (L5)', function () {
        Queue::fake([FinishPayment::class]);
        $booking = heldWithDetails();
        startPaying($booking);
        $payment = latestPayment();
        $this->gateway->pay($payment->gateway_order_id);
        TestGateway::failNext('capture');
        comeBack($payment);

        (new FinishPayment($payment->id))->failed(new RuntimeException('Gateway down all day.'));

        expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled)
            ->and($booking->fresh()->cancelled_by)->toBe('system')
            ->and($payment->fresh()->status)->toBe(PaymentStatus::Released)
            ->and($payment->fresh()->outcome)->toBe(PaymentOutcome::CaptureFailed)
            ->and($this->gateway->load($payment->gateway_order_id)['state'])->toBe('released');

        Mail::assertQueued(PaymentReturnedMail::class, 1);
    });

    it('retries a refund the gateway refused, refunding exactly once (C3, C5)', function () {
        Queue::fake([SendRefund::class]);
        $booking = heldWithDetails();
        startPaying($booking);
        $payment = latestPayment();
        $booking->update(['status' => BookingStatus::Expired]);
        someoneElseBooks($booking);
        $this->gateway->pay($payment->gateway_order_id, capture: true);
        TestGateway::failNext('refund');

        comeBack($payment);

        expect(Refund::sole()->status)->toBe(RefundStatus::Pending)
            ->and(Refund::sole()->attempts)->toBe(1);
        Queue::assertPushed(SendRefund::class);

        app()->call([new SendRefund(Refund::sole()->id), 'handle']);
        app(RefundPayment::class)->handle(Refund::sole());

        expect(Refund::sole()->status)->toBe(RefundStatus::Succeeded)
            ->and($payment->fresh()->refunded_amount)->toBe(2000)
            ->and($this->gateway->load($payment->gateway_order_id)['refunded'])->toBe(2000)
            ->and(LedgerEntry::where('type', 'refund')->count())->toBe(1);
    });
});

describe('payments that should not count', function () {
    it('stops the first checkout when a second one starts, so it cannot be paid (P3)', function () {
        $booking = heldWithDetails();
        startPaying($booking);
        $first = latestPayment();
        startPaying($booking);

        $this->gateway->pay($first->gateway_order_id);

        expect($this->gateway->load($first->gateway_order_id)['state'])->toBe('cancelled');
    });

    it('gives back a second payment for a booking that is already paid (L6)', function () {
        $booking = heldWithDetails();
        startPaying($booking);
        $first = latestPayment();
        // The first checkout couldn't be stopped (gateway unreachable), so it stays payable.
        TestGateway::failNext('release');
        startPaying($booking);
        $second = latestPayment();

        // Both get paid: the second one settles first.
        $this->gateway->pay($first->gateway_order_id);
        $this->gateway->pay($second->gateway_order_id);
        comeBack($second);
        app(SettlePayment::class)->handle($first->fresh());

        expect($second->fresh()->status)->toBe(PaymentStatus::Captured)
            ->and($first->fresh()->outcome)->toBe(PaymentOutcome::Duplicate)
            ->and($first->fresh()->status)->toBe(PaymentStatus::Released)
            ->and(LedgerEntry::sum('amount'))->toBe(2000);
    });

    it('gives back money in a different amount than asked, without confirming (P5)', function () {
        $booking = heldWithDetails();
        startPaying($booking);
        $payment = latestPayment();
        $this->gateway->pay($payment->gateway_order_id);

        app(SettlePayment::class)->handle($payment, new GatewayStatus(GatewayState::Authorized, 'test_pay_x', 1, 'GBP'));

        expect($payment->fresh()->outcome)->toBe(PaymentOutcome::AmountMismatch)
            ->and($payment->fresh()->status)->toBe(PaymentStatus::Released)
            ->and($booking->fresh()->status)->toBe(BookingStatus::Held);

        // Something for us to look into, not an email for the customer.
        Mail::assertNotQueued(PaymentReturnedMail::class);
    });
});
