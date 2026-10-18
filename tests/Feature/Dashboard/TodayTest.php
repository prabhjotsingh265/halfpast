<?php

use App\Enums\BookingStatus;
use App\Enums\BusinessRole;
use App\Enums\PaymentOutcome;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Models\WorkingHour;
use App\Tenancy\CurrentBusiness;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Tuesday 27 October 2026, 10:00 in London (GMT, so UTC and local agree).
 * The week runs Monday 26 to Sunday 1 November.
 */

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-27 10:00', 'UTC'));

    $this->owner = User::factory()->create();
    $this->business = Business::factory()->ownedBy($this->owner)->create(['timezone' => 'Europe/London', 'currency' => 'GBP', 'payment_gateways' => ['test']]);
    app(CurrentBusiness::class)->set($this->business);

    $this->priyaUser = User::factory()->create(['name' => 'Priya Sharma']);
    $this->business->addMember($this->priyaUser, BusinessRole::Staff);
    $this->priya = Staff::factory()->for($this->business)->create(['name' => 'Priya', 'user_id' => $this->priyaUser->id, 'position' => 1]);
    $this->sam = Staff::factory()->for($this->business)->create(['name' => 'Sam', 'position' => 2]);
});

function todayBooking(Staff $staff, string $start, string $end, array $override = []): Booking
{
    return Booking::factory()->for(test()->business)->at("{$start} UTC", "{$end} UTC")->create([
        'staff_id' => $staff->id,
        'customer_id' => Customer::factory()->for(test()->business)->create()->id,
        'price' => 3000,
        ...$override,
    ]);
}

function dashboardFor(User $user): array
{
    return test()->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('page')['props'];
}

it("shows today's appointments in order, with what's left and what's next", function () {
    $early = todayBooking($this->priya, '2026-10-27 08:00', '2026-10-27 09:00');
    $onNow = todayBooking($this->sam, '2026-10-27 09:30', '2026-10-27 10:30');
    $next = todayBooking($this->sam, '2026-10-27 11:00', '2026-10-27 12:00');
    $paid = todayBooking($this->priya, '2026-10-27 14:00', '2026-10-27 15:00', ['deposit' => 1000]);
    Payment::factory()->create(['booking_id' => $paid->id, 'business_id' => $this->business->id, 'amount' => 1000, 'currency' => 'GBP', 'status' => PaymentStatus::Captured, 'outcome' => PaymentOutcome::Confirmed]);
    $hold = todayBooking($this->priya, '2026-10-27 16:00', '2026-10-27 17:00', ['status' => BookingStatus::Held, 'hold_expires_at' => now()->addMinutes(10)]);
    todayBooking($this->priya, '2026-10-27 12:00', '2026-10-27 13:00', ['status' => BookingStatus::Cancelled]);
    todayBooking($this->sam, '2026-10-28 09:00', '2026-10-28 10:00', ['price' => 5000]);
    todayBooking($this->sam, '2026-11-02 09:00', '2026-11-02 10:00');

    $props = dashboardFor($this->owner);

    expect(collect($props['bookings'])->pluck('code')->all())->toBe([$early->code, $onNow->code, $next->code, $paid->code, $hold->code])
        ->and($props['next']['code'])->toBe($next->code)
        ->and($props['stats'])->toMatchArray([
            'booked' => 4,
            'left' => 3,
            'onNow' => 1,
            // Still to come today, less the £10 deposit already paid.
            'toTake' => 3000 + 3000 + 2000,
            // Today's four, plus tomorrow's; next week's doesn't count.
            'weekBookings' => 5,
            'weekValue' => 3000 * 4 + 5000,
            'currency' => 'GBP',
        ])
        ->and($props['isOwner'])->toBeTrue()
        ->and($props['linked'])->toBeTrue()
        ->and($props['bookingPageUrl'])->toBe(route('booking.show', ['business' => $this->business->slug]));
});

it('says nothing is next once the day is done', function () {
    todayBooking($this->priya, '2026-10-27 08:00', '2026-10-27 09:00');

    $props = dashboardFor($this->owner);

    expect($props['next'])->toBeNull()
        ->and($props['stats']['left'])->toBe(0)
        ->and($props['stats']['toTake'])->toBe(0);
});

it('lists finished appointments nobody has marked yet, from the last two weeks', function () {
    $yesterday = todayBooking($this->priya, '2026-10-26 15:00', '2026-10-26 16:00');
    $thisMorning = todayBooking($this->sam, '2026-10-27 08:00', '2026-10-27 09:00');
    todayBooking($this->sam, '2026-10-27 09:30', '2026-10-27 10:30');
    todayBooking($this->priya, '2026-10-25 15:00', '2026-10-25 16:00', ['status' => BookingStatus::Completed]);
    todayBooking($this->priya, '2026-10-01 15:00', '2026-10-01 16:00');

    $props = dashboardFor($this->owner);

    expect(collect($props['unmarked'])->pluck('code')->all())->toBe([$thisMorning->code, $yesterday->code])
        ->and($props['unmarkedCount'])->toBe(2)
        ->and($props['unmarked'][0]['can']['mark'])->toBeTrue();
});

it('shows the latest few waiting to be marked, and how many there are', function () {
    // One unmarked appointment on each of the ten days before today.
    foreach (range(1, 10) as $daysAgo) {
        $day = CarbonImmutable::parse('2026-10-27')->subDays($daysAgo)->toDateString();
        todayBooking($this->priya, "{$day} 08:00", "{$day} 08:30");
    }

    $props = dashboardFor($this->owner);

    expect($props['unmarked'])->toHaveCount(8)
        ->and($props['unmarked'][0]['starts_at'])->toBe('2026-10-26T08:00:00Z')
        ->and($props['unmarkedCount'])->toBe(10);
});

it('shows the latest online bookings, newest first', function () {
    $older = todayBooking($this->priya, '2026-10-30 09:00', '2026-10-30 10:00', ['created_at' => now()->subDay()]);
    $newer = todayBooking($this->sam, '2026-11-03 09:00', '2026-11-03 10:00', ['created_at' => now()->subMinutes(5)]);
    todayBooking($this->sam, '2026-10-29 09:00', '2026-10-29 10:00', ['source' => 'manual']);
    todayBooking($this->sam, '2026-10-29 11:00', '2026-10-29 12:00', ['status' => BookingStatus::Held, 'hold_expires_at' => now()->addMinutes(5)]);

    $props = dashboardFor($this->owner);

    expect(collect($props['recent'])->pluck('code')->all())->toBe([$newer->code, $older->code])
        ->and($props['recent'][0]['booked_at'])->toBe('2026-10-27T09:55:00Z');
});

it('flags a refund that never went through, and services nobody can pay a deposit for', function () {
    $this->business->update(['payment_gateways' => []]);
    Service::factory()->for($this->business)->withFixedDeposit(1000)->create(['name' => 'Colour']);
    Service::factory()->for($this->business)->create(['name' => 'Trim']);

    $booking = todayBooking($this->priya, '2026-10-20 09:00', '2026-10-20 10:00', ['customer_id' => Customer::factory()->for($this->business)->create(['name' => 'Ananya'])->id]);
    $payment = Payment::factory()->create(['booking_id' => $booking->id, 'business_id' => $this->business->id, 'amount' => 1000, 'currency' => 'GBP', 'gateway' => 'razorpay', 'status' => PaymentStatus::Captured]);
    $refund = new Refund(['payment_id' => $payment->id, 'amount' => 1000, 'status' => RefundStatus::Failed, 'reason' => 'customer_cancelled']);
    $refund->business_id = $this->business->id;
    $refund->save();

    $attention = dashboardFor($this->owner)['attention'];

    expect($attention['blockedServices'])->toBe(['Colour'])
        ->and($attention['failedRefunds'])->toBe([['amount' => 1000, 'currency' => 'GBP', 'customer' => 'Ananya', 'gateway' => 'razorpay']]);
});

it('walks a new business through setting up, then gets out of the way', function () {
    expect(dashboardFor($this->owner)['setup'])->toBe(['services' => false, 'team' => true, 'hours' => false, 'assigned' => false, 'payments' => true]);

    $service = Service::factory()->for($this->business)->create();
    $service->staff()->attach($this->priya);
    $hour = new WorkingHour(['staff_id' => $this->priya->id, 'weekday' => 2, 'starts_at' => '09:00', 'ends_at' => '17:00']);
    $hour->business_id = $this->business->id;
    $hour->save();

    expect(dashboardFor($this->owner)['setup'])->toBeNull();
});

it('keeps taking deposits optional in the checklist', function () {
    $this->business->update(['payment_gateways' => []]);
    $service = Service::factory()->for($this->business)->create();
    $service->staff()->attach($this->priya);
    $hour = new WorkingHour(['staff_id' => $this->priya->id, 'weekday' => 2, 'starts_at' => '09:00', 'ends_at' => '17:00']);
    $hour->business_id = $this->business->id;
    $hour->save();

    expect(dashboardFor($this->owner)['setup'])->toBeNull();
});

it('shows staff their own diary only, and none of the owner things', function () {
    $mine = todayBooking($this->priya, '2026-10-27 11:00', '2026-10-27 12:00');
    todayBooking($this->sam, '2026-10-27 11:00', '2026-10-27 12:00');
    todayBooking($this->sam, '2026-10-27 08:00', '2026-10-27 09:00');

    $props = dashboardFor($this->priyaUser);

    expect(collect($props['bookings'])->pluck('code')->all())->toBe([$mine->code])
        ->and($props['stats']['booked'])->toBe(1)
        ->and($props['unmarked'])->toBe([])
        ->and($props['isOwner'])->toBeFalse()
        ->and($props['myStaffIds'])->toBe([$this->priya->id])
        ->and($props['attention'])->toBeNull()
        ->and($props['setup'])->toBeNull();
});

it('tells staff whose login is not linked to the team', function () {
    $loose = User::factory()->create();
    $this->business->addMember($loose, BusinessRole::Staff);
    todayBooking($this->sam, '2026-10-27 11:00', '2026-10-27 12:00');

    $props = dashboardFor($loose);

    expect($props['linked'])->toBeFalse()
        ->and($props['bookings'])->toBe([]);
});

it("never shows another business's bookings", function () {
    $other = Business::factory()->create();
    Booking::factory()->for($other)->at('2026-10-27 11:00 UTC', '2026-10-27 12:00 UTC')->create();

    $props = dashboardFor($this->owner);

    expect($props['bookings'])->toBe([])
        ->and($props['stats']['weekBookings'])->toBe(0);
});

it('renders the dashboard page', function () {
    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard')->where('today', '2026-10-27')->where('timezone', 'Europe/London'));
});
