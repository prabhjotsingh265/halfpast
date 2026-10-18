<?php

use App\Actions\Booking\AddBooking;
use App\Actions\Booking\CancelBooking;
use App\Actions\Booking\ConfirmBooking;
use App\Enums\BookingStatus;
use App\Enums\BusinessRole;
use App\Enums\PaymentOutcome;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Jobs\FinishPayment;
use App\Models\Booking;
use App\Models\BookingReminder;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Tenancy\CurrentBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

/*
 * Tuesday 27 October 2026 in London (GMT), with a £10 deposit.
 */

beforeEach(function () {
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-27 07:00', 'UTC'));
    $this->owner = User::factory()->create(['name' => 'Olivia Owner']);
    $this->business = Business::factory()->ownedBy($this->owner)->create(['timezone' => 'Europe/London', 'currency' => 'GBP', 'cancellation_window_hours' => 24]);
    app(CurrentBusiness::class)->set($this->business);
    $this->staff = Staff::factory()->for($this->business)->create(['name' => 'Priya']);
});

function historyOf(Booking $booking, ?User $as = null): array
{
    return test()->actingAs($as ?? test()->owner)
        ->getJson(route('bookings.history', $booking))
        ->assertOk()
        ->json();
}

function paidBooking(string $start = '2026-10-27 09:00', array $override = []): Booking
{
    $end = CarbonImmutable::parse("{$start} UTC")->addHour()->format('Y-m-d H:i');

    return Booking::factory()->for(test()->business)->held(60)->at("{$start} UTC", "{$end} UTC")->create([
        'staff_id' => test()->staff->id,
        'customer_id' => Customer::factory()->for(test()->business)->create()->id,
        'deposit' => 1000,
        'currency' => 'GBP',
        ...$override,
    ]);
}

it('tells the story of a booking from start to finish, oldest first', function () {
    $booking = paidBooking();

    $this->travelTo(CarbonImmutable::parse('2026-10-27 07:05', 'UTC'));
    Payment::factory()->create(['booking_id' => $booking->id, 'business_id' => $this->business->id, 'amount' => 1000, 'currency' => 'GBP', 'gateway' => 'test', 'status' => PaymentStatus::Captured, 'captured_at' => now()]);

    $this->travelTo(CarbonImmutable::parse('2026-10-27 07:06', 'UTC'));
    app(ConfirmBooking::class)->handle($booking);

    (new BookingReminder)->forceFill([
        'business_id' => $this->business->id,
        'booking_id' => $booking->id,
        'kind' => '2h',
        'sent_at' => CarbonImmutable::parse('2026-10-27 07:10', 'UTC'),
    ])->save();

    $this->travelTo(CarbonImmutable::parse('2026-10-27 10:00', 'UTC'));
    $this->actingAs($this->owner)->post(route('bookings.complete', $booking));

    $history = historyOf($booking);

    expect(array_column($history, 'text'))->toBe([
        'Booked online',
        'Deposit of £10 paid by test payment',
        'Confirmed',
        '2-hour reminder sent',
        'Marked completed by Olivia Owner',
    ])
        ->and(array_column($history, 'tone'))->toBe(['neutral', 'good', 'good', 'neutral', 'good'])
        ->and($history[0]['at'])->toBe('2026-10-27T07:00:00Z')
        ->and($history[4]['at'])->toBe('2026-10-27T10:00:00Z');
});

it('says who cancelled and what happened to the deposit', function () {
    $booking = paidBooking('2026-10-30 10:00');
    app(ConfirmBooking::class)->handle($booking);
    $payment = Payment::factory()->create(['booking_id' => $booking->id, 'business_id' => $this->business->id, 'amount' => 1000, 'currency' => 'GBP', 'gateway' => 'stripe', 'status' => PaymentStatus::Captured, 'captured_at' => now()]);

    $this->actingAs($this->owner);
    app(CancelBooking::class)->handle($booking, 'business', refund: true, tellCustomer: false);
    $refund = new Refund(['payment_id' => $payment->id, 'amount' => 1000, 'status' => RefundStatus::Succeeded, 'reason' => 'business_cancelled']);
    $refund->business_id = $this->business->id;
    $refund->save();

    expect(array_column(historyOf($booking), 'text'))->toBe([
        'Booked online',
        'Deposit of £10 paid by card',
        'Confirmed',
        'Cancelled by Olivia Owner',
        '£10 refunded',
    ]);
});

it('notes when a late cancellation keeps the deposit', function () {
    $booking = paidBooking('2026-10-27 12:00');
    app(ConfirmBooking::class)->handle($booking);

    // The customer, through their own link: nobody is logged in.
    app(CancelBooking::class)->handle($booking, 'customer');

    expect(array_column(historyOf($booking), 'text'))->toContain('Cancelled by the customer, keeping the deposit');
});

it('names who added a booking by hand', function () {
    $priyaUser = User::factory()->create(['name' => 'Priya Sharma']);
    $this->business->addMember($priyaUser, BusinessRole::Staff);
    $this->staff->update(['user_id' => $priyaUser->id]);
    $service = Service::factory()->for($this->business)->create(['duration_minutes' => 30]);
    $service->staff()->attach($this->staff);

    $this->actingAs($priyaUser);
    $booking = app(AddBooking::class)->handle($this->business, $service, $this->staff, CarbonImmutable::parse('2026-10-27 11:00', 'UTC'), tellCustomer: false);

    expect(array_column(historyOf($booking, $priyaUser), 'text'))->toBe(['Added by Priya Sharma']);
});

it('still has a start for bookings made before history was kept', function () {
    $booking = Booking::factory()->for($this->business)->at('2026-10-20 09:00 UTC', '2026-10-20 10:00 UTC')->create([
        'staff_id' => $this->staff->id,
        'source' => 'manual',
        'created_at' => '2026-10-15 12:00',
    ]);
    $booking->events()->delete();

    expect(historyOf($booking))->toBe([['at' => '2026-10-15T12:00:00Z', 'text' => 'Added by the business', 'tone' => 'neutral']]);
});

it("records the system cancelling a booking whose deposit couldn't be taken", function () {
    $booking = paidBooking('2026-10-29 10:00');
    app(ConfirmBooking::class)->handle($booking);
    $payment = Payment::factory()->create(['booking_id' => $booking->id, 'business_id' => $this->business->id, 'amount' => 1000, 'currency' => 'GBP', 'gateway' => 'test', 'gateway_order_id' => 'test_order_missing', 'status' => PaymentStatus::Authorized, 'outcome' => PaymentOutcome::Confirmed, 'authorized_at' => now()]);

    (new FinishPayment($payment->id))->failed(new RuntimeException('Still down.'));

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled)
        ->and(array_column(historyOf($booking), 'text'))->toBe([
            'Booked online',
            'Confirmed',
            "Cancelled: the deposit couldn't be taken",
            "A £10 payment was let go, so it wasn't charged",
        ]);
});

it('is open to the team, and to no one else', function () {
    $booking = paidBooking();
    $staffUser = User::factory()->create();
    $this->business->addMember($staffUser, BusinessRole::Staff);
    $outsider = User::factory()->create();
    Business::factory()->ownedBy($outsider)->create();

    $this->actingAs($staffUser)->getJson(route('bookings.history', $booking))->assertOk();
    $this->actingAs($outsider)->getJson(route('bookings.history', $booking->code))->assertNotFound();
    auth()->logout();
    $this->getJson(route('bookings.history', $booking->code))->assertUnauthorized();
});
