<?php

use App\Models\Booking;
use App\Models\Business;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * It's Monday 26 October 2026, 08:00 in London. Glow Studio takes bookings
 * from two hours ahead, on the hour. Priya works Monday to Friday, 9 to 12.
 */

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00', 'UTC'));

    $this->business = Business::factory()->create([
        'name' => 'Glow Studio',
        'slug' => 'glow-studio',
        'timezone' => 'Europe/London',
        'currency' => 'GBP',
        'slot_step_minutes' => 60,
        'min_notice_minutes' => 120,
        'booking_horizon_days' => 30,
    ]);
    $this->haircut = Service::factory()->for($this->business)->withPercentDeposit(20)
        ->create(['name' => 'Haircut', 'duration_minutes' => 60, 'price' => 3000]);
    $this->priya = Staff::factory()->for($this->business)->create(['name' => 'Priya']);
    $this->haircut->staff()->attach($this->priya);

    foreach (range(1, 5) as $weekday) {
        $hour = new WorkingHour(['staff_id' => $this->priya->id, 'weekday' => $weekday, 'starts_at' => '09:00', 'ends_at' => '12:00']);
        $hour->business_id = $this->business->id;
        $hour->save();
    }
});

it('shows what can be booked, to anyone', function () {
    // Switched off, or with no one to do it: not shown.
    Service::factory()->for($this->business)->create(['is_active' => false])->staff()->attach($this->priya);
    Service::factory()->for($this->business)->create(['name' => 'Nobody does this']);

    $this->get('/glow-studio')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('booking/Show')
            ->where('business.name', 'Glow Studio')
            ->where('business.role', null)
            ->has('services', 1)
            ->where('services.0.name', 'Haircut')
            ->where('services.0.deposit', 600)
            ->where('services.0.staff_ids', [$this->priya->id])
            ->where('staff.0.name', 'Priya')
            ->where('today', '2026-10-26')
            ->where('days', null)
            ->where('times', null));
});

it('lists free days and times once a service is picked', function () {
    Booking::factory()->for($this->business)->at('2026-10-27 09:00', '2026-10-27 10:00')->create(['staff_id' => $this->priya->id]);

    $this->get('/glow-studio?service='.$this->haircut->id)
        ->assertInertia(fn (Assert $page) => $page
            ->where('selection.service_id', $this->haircut->id)
            ->where('selection.staff_id', null)
            ->where('selection.from', '2026-10-26')
            // Today only has 10:00 and 11:00 left after two hours' notice.
            ->where('selection.date', '2026-10-26')
            ->has('days', 14)
            ->where('days.0', ['date' => '2026-10-26', 'free' => 2])
            ->where('days.1', ['date' => '2026-10-27', 'free' => 2])
            ->where('days.5', ['date' => '2026-10-31', 'free' => 0])
            ->has('times', 2)
            ->where('times.0.starts_at', '2026-10-26T10:00:00Z')
            ->where('times.0.staff_ids', [$this->priya->id]));
});

it('shows the times on the day picked, for the person picked', function () {
    $this->get("/glow-studio?service={$this->haircut->id}&staff={$this->priya->id}&date=2026-10-29")
        ->assertInertia(fn (Assert $page) => $page
            ->where('selection.staff_id', $this->priya->id)
            ->where('selection.date', '2026-10-29')
            ->where('times', [
                ['starts_at' => '2026-10-29T09:00:00Z', 'staff_ids' => [$this->priya->id]],
                ['starts_at' => '2026-10-29T10:00:00Z', 'staff_ids' => [$this->priya->id]],
                ['starts_at' => '2026-10-29T11:00:00Z', 'staff_ids' => [$this->priya->id]],
            ]));
});

it('looks further ahead a fortnight at a time, but not past the horizon', function () {
    $this->get("/glow-studio?service={$this->haircut->id}&from=2026-11-09")
        ->assertInertia(fn (Assert $page) => $page
            ->where('selection.from', '2026-11-09')
            ->where('selection.date', '2026-11-09')
            ->where('lastDay', '2026-11-25')
            ->where('days.13', ['date' => '2026-11-22', 'free' => 0]));

    $this->get("/glow-studio?service={$this->haircut->id}&from=2027-06-01")
        ->assertInertia(fn (Assert $page) => $page->where('selection.from', '2026-11-25'));
});

it('ignores choices that are not on offer', function () {
    $other = Business::factory()->create();
    $theirService = Service::factory()->for($other)->create();
    $theirStaff = Staff::factory()->for($other)->create();

    $this->get("/glow-studio?service={$theirService->id}&staff={$theirStaff->id}&date=not-a-date")
        ->assertInertia(fn (Assert $page) => $page
            ->where('selection.service_id', null)
            ->where('selection.staff_id', null)
            ->where('days', null));
});

it('shows the business at the address, even to the owner of another', function () {
    $owner = User::factory()->create();
    Business::factory()->ownedBy($owner)->create(['name' => 'Their Place']);

    $this->actingAs($owner)
        ->get('/glow-studio')
        ->assertInertia(fn (Assert $page) => $page
            ->where('business.name', 'Glow Studio')
            ->where('business.role', null));
});

it('is a 404 for an address no business has', function () {
    $this->get('/no-such-place')->assertNotFound();
});

it('leaves the app\'s own pages alone', function () {
    $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page->component('auth/Login'));
    $this->get('/up')->assertOk();
});
