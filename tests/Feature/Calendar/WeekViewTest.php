<?php

use App\Http\Controllers\CalendarController;
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
 * Tuesday 27 October 2026 in London. The clocks went back on Sunday 25th,
 * so the week before has a 25-hour day in it.
 */

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-27 08:00', 'UTC'));
    $this->owner = User::factory()->create();
    $this->business = Business::factory()->ownedBy($this->owner)->create(['timezone' => 'Europe/London']);
    $this->priya = Staff::factory()->for($this->business)->create(['name' => 'Priya', 'position' => 1]);
    $this->sam = Staff::factory()->for($this->business)->create(['name' => 'Sam', 'position' => 2]);

    foreach ([[$this->priya, 1, '09:00', '17:00'], [$this->sam, 6, '10:00', '14:00']] as [$staff, $weekday, $from, $to]) {
        $hour = new WorkingHour(['staff_id' => $staff->id, 'weekday' => $weekday, 'starts_at' => $from, 'ends_at' => $to]);
        $hour->business_id = $this->business->id;
        $hour->save();
    }
});

/** Times are UTC. */
function weekBooking(Staff $staff, string $start, string $end): Booking
{
    return Booking::factory()->for(test()->business)->at("{$start} UTC", "{$end} UTC")->create([
        'staff_id' => $staff->id,
        'customer_id' => Customer::factory()->for(test()->business)->create()->id,
    ]);
}

it('shows Monday to Sunday around the date', function () {
    $monday = weekBooking($this->priya, '2026-10-26 09:00', '2026-10-26 10:00');
    $sunday = weekBooking($this->sam, '2026-11-01 17:00', '2026-11-01 18:00');
    weekBooking($this->priya, '2026-10-25 12:00', '2026-10-25 13:00');
    weekBooking($this->priya, '2026-11-02 09:00', '2026-11-02 10:00');

    $this->actingAs($this->owner)
        ->get(route('calendar', ['view' => 'week', 'date' => '2026-10-28']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('calendar/Index')
            ->where('view', 'week')
            ->where('date', '2026-10-28')
            ->where('days', ['2026-10-26', '2026-10-27', '2026-10-28', '2026-10-29', '2026-10-30', '2026-10-31', '2026-11-01'])
            ->where('bookings', fn ($bookings) => collect($bookings)->pluck('code')->all() === [$monday->code, $sunday->code]));
});

it('starts the week on the Monday before a Sunday', function () {
    $this->actingAs($this->owner)
        ->get(route('calendar', ['view' => 'week', 'date' => '2026-11-01']))
        ->assertInertia(fn (Assert $page) => $page->where('days.0', '2026-10-26')->where('days.6', '2026-11-01'));
});

it('runs midnight to midnight in local time through a clock change', function () {
    // Monday 19th, 00:30 BST is still Sunday in UTC.
    $first = weekBooking($this->priya, '2026-10-18 23:30', '2026-10-19 00:00');
    // Sunday 25th, 23:00 GMT, after the clocks went back.
    $last = weekBooking($this->priya, '2026-10-25 23:00', '2026-10-25 23:30');
    // Monday 26th, 00:30 GMT: the next week.
    weekBooking($this->priya, '2026-10-26 00:30', '2026-10-26 01:00');
    // Sunday 18th, 23:30 BST: the week before.
    weekBooking($this->priya, '2026-10-18 22:30', '2026-10-18 23:00');

    $this->actingAs($this->owner)
        ->get(route('calendar', ['view' => 'week', 'date' => '2026-10-21']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.0', '2026-10-19')
            ->where('days.6', '2026-10-25')
            ->where('bookings', fn ($bookings) => collect($bookings)->pluck('code')->all() === [$first->code, $last->code]));
});

it("gives each person's working hours for every day of the week", function () {
    $this->actingAs($this->owner)
        ->get(route('calendar', ['view' => 'week', 'date' => '2026-10-27']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('staff.0.name', 'Priya')
            ->where('staff.0.hours.2026-10-26', [[540, 1020]])
            ->where('staff.0.hours.2026-10-27', [])
            ->where('staff.1.hours.2026-10-31', [[600, 840]])
            ->has('staff.1.hours', 7));
});

it('shows the same week as a list', function () {
    weekBooking($this->sam, '2026-10-31 10:00', '2026-10-31 11:00');

    $this->actingAs($this->owner)
        ->get(route('calendar', ['view' => 'list', 'date' => '2026-10-27']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('view', 'list')
            ->where('days.0', '2026-10-26')
            ->has('days', 7)
            ->has('bookings', 1));
});

it('remembers the last view used', function () {
    $this->actingAs($this->owner)->get(route('calendar', ['view' => 'list']))->assertOk();

    expect(session(CalendarController::SESSION_VIEW))->toBe('list');

    $this->get(route('calendar'))->assertInertia(fn (Assert $page) => $page->where('view', 'list'));
    $this->get(route('calendar', ['view' => 'month']))->assertInertia(fn (Assert $page) => $page->where('view', 'list'));
    $this->get(route('calendar', ['view' => 'day']))->assertInertia(fn (Assert $page) => $page->where('view', 'day')->has('days', 1));
});

it('opens on the day view the first time', function () {
    $this->actingAs($this->owner)
        ->get(route('calendar'))
        ->assertInertia(fn (Assert $page) => $page->where('view', 'day')->where('days', ['2026-10-27']));
});

it('narrows any view to one person', function (string $view) {
    weekBooking($this->priya, '2026-10-27 09:00', '2026-10-27 10:00');
    $sams = weekBooking($this->sam, '2026-10-27 11:00', '2026-10-27 12:00');
    TimeOff::factory()->for($this->business)->create(['staff_id' => $this->priya->id, 'starts_at' => '2026-10-27 13:00', 'ends_at' => '2026-10-27 14:00']);
    $samOff = TimeOff::factory()->for($this->business)->create(['staff_id' => $this->sam->id, 'starts_at' => '2026-10-27 14:00', 'ends_at' => '2026-10-27 15:00']);
    $closed = TimeOff::factory()->for($this->business)->create(['staff_id' => null, 'starts_at' => '2026-10-27 16:00', 'ends_at' => '2026-10-27 17:00']);

    $this->actingAs($this->owner)
        ->get(route('calendar', ['view' => $view, 'date' => '2026-10-27', 'staff' => $this->sam->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('person', $this->sam->id)
            ->where('staff', fn ($staff) => collect($staff)->pluck('name')->all() === ['Sam'])
            ->where('team', fn ($team) => collect($team)->pluck('name')->all() === ['Priya', 'Sam'])
            ->where('bookings', fn ($bookings) => collect($bookings)->pluck('code')->all() === [$sams->code])
            ->where('timeOff', fn ($off) => collect($off)->pluck('id')->sort()->values()->all() === collect([$samOff->id, $closed->id])->sort()->values()->all()));
})->with(['day', 'week', 'list']);

it('keeps someone no longer taking bookings in the list while they are picked', function () {
    $this->sam->update(['is_active' => false]);

    $this->actingAs($this->owner)
        ->get(route('calendar', ['staff' => $this->sam->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('person', $this->sam->id)
            ->where('team', fn ($team) => collect($team)->pluck('name')->all() === ['Priya', 'Sam']));
});

it("ignores a person who isn't on this team", function (mixed $staff) {
    $outsider = Staff::factory()->for(Business::factory())->create();
    weekBooking($this->priya, '2026-10-27 09:00', '2026-10-27 10:00');

    $this->actingAs($this->owner)
        ->get(route('calendar').'?staff='.($staff === 'outsider' ? $outsider->id : $staff))
        ->assertInertia(fn (Assert $page) => $page
            ->where('person', null)
            ->has('staff', 2)
            ->has('bookings', 1));
})->with(['outsider', 'abc', '-1', '99999999999999']);
