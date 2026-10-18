<?php

use App\Enums\BookingStatus;
use App\Enums\BusinessRole;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Staff;
use App\Models\TimeOff;
use App\Models\User;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Tuesday 27 October 2026 in London (GMT by then).
 */

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-27 08:00', 'UTC'));
    $this->owner = User::factory()->create();
    $this->business = Business::factory()->ownedBy($this->owner)->create(['timezone' => 'Europe/London']);
    $this->priya = Staff::factory()->for($this->business)->create(['name' => 'Priya', 'position' => 1]);
    $this->sam = Staff::factory()->for($this->business)->create(['name' => 'Sam', 'position' => 2]);

    foreach ([[$this->priya, '09:00', '13:00'], [$this->priya, '14:00', '18:00'], [$this->sam, '10:00', '16:00']] as [$staff, $from, $to]) {
        $hour = new WorkingHour(['staff_id' => $staff->id, 'weekday' => 2, 'starts_at' => $from, 'ends_at' => $to]);
        $hour->business_id = $this->business->id;
        $hour->save();
    }
});

function calendarBooking(Staff $staff, string $start, string $end, array $override = []): Booking
{
    return Booking::factory()->for(test()->business)->at($start, $end)->create([
        'staff_id' => $staff->id,
        'customer_id' => Customer::factory()->for(test()->business)->create(['name' => 'Ananya'])->id,
        ...$override,
    ]);
}

it("shows the day's bookings, hours and time off for each person", function () {
    $confirmed = calendarBooking($this->priya, '2026-10-27 09:00', '2026-10-27 10:00', ['service_name' => 'Colour']);
    $held = calendarBooking($this->sam, '2026-10-27 11:00', '2026-10-27 11:30', ['status' => BookingStatus::Held, 'hold_expires_at' => now()->addMinutes(10)]);
    calendarBooking($this->priya, '2026-10-27 12:00', '2026-10-27 12:30', ['status' => BookingStatus::Cancelled]);
    calendarBooking($this->priya, '2026-10-27 15:00', '2026-10-27 15:30', ['status' => BookingStatus::Held, 'hold_expires_at' => now()->subMinute()]);
    calendarBooking($this->priya, '2026-10-28 09:00', '2026-10-28 10:00');
    TimeOff::factory()->for($this->business)->create(['staff_id' => $this->sam->id, 'starts_at' => '2026-10-27 13:00', 'ends_at' => '2026-10-27 14:00', 'reason' => 'Dentist']);

    $this->actingAs($this->owner)
        ->get(route('calendar', ['date' => '2026-10-27']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('calendar/Index')
            ->where('date', '2026-10-27')
            ->where('today', '2026-10-27')
            ->where('timezone', 'Europe/London')
            ->where('staff.0.name', 'Priya')
            ->where('staff.0.hours.2026-10-27', [[540, 780], [840, 1080]])
            ->where('staff.1.hours.2026-10-27', [[600, 960]])
            ->has('bookings', 2)
            ->where('bookings.0.code', $confirmed->code)
            ->where('bookings.0.service_name', 'Colour')
            ->where('bookings.0.customer.name', 'Ananya')
            ->where('bookings.1.code', $held->code)
            ->where('bookings.1.status', 'held')
            ->has('timeOff', 1)
            ->where('timeOff.0.reason', 'Dentist'));
});

it('keeps a column for someone no longer taking bookings who has one that day', function () {
    $this->sam->update(['is_active' => false]);
    $gone = Staff::factory()->for($this->business)->create(['name' => 'Alex', 'is_active' => false]);
    calendarBooking($this->sam, '2026-10-27 11:00', '2026-10-27 12:00');

    $this->actingAs($this->owner)
        ->get(route('calendar', ['date' => '2026-10-27']))
        ->assertInertia(fn (Assert $page) => $page->where('staff', fn ($staff) => collect($staff)->pluck('name')->all() === ['Priya', 'Sam']));

    expect($gone->exists)->toBeTrue();
});

it('shows a whole-business closure', function () {
    TimeOff::factory()->for($this->business)->create(['staff_id' => null, 'starts_at' => '2026-10-27 00:00', 'ends_at' => '2026-10-28 00:00', 'reason' => 'Diwali']);

    $this->actingAs($this->owner)
        ->get(route('calendar', ['date' => '2026-10-27']))
        ->assertInertia(fn (Assert $page) => $page->where('timeOff.0.staff_id', null)->where('timeOff.0.reason', 'Diwali'));
});

it('opens on today, and ignores a date it cannot read', function () {
    $this->actingAs($this->owner)
        ->get(route('calendar', ['date' => 'next-tuesday']))
        ->assertInertia(fn (Assert $page) => $page->where('date', '2026-10-27'));
});

it('can be seen by staff as well as owners', function () {
    $staffUser = User::factory()->create();
    $this->business->addMember($staffUser, BusinessRole::Staff);

    $this->actingAs($staffUser)->get(route('calendar'))->assertOk();
});

it("never shows another business's bookings", function () {
    $other = Business::factory()->create();
    Booking::factory()->for($other)->at('2026-10-27 09:00', '2026-10-27 10:00')->create();

    $this->actingAs($this->owner)
        ->get(route('calendar', ['date' => '2026-10-27']))
        ->assertInertia(fn (Assert $page) => $page->has('bookings', 0));
});

it('needs a login', function () {
    $this->get(route('calendar'))->assertRedirect(route('login'));
});
