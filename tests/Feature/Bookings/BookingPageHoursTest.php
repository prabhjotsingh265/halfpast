<?php

use App\Actions\Staff\SaveWeeklyHours;
use App\Models\Business;
use App\Models\Service;
use App\Models\Staff;
use App\Tenancy\CurrentBusiness;
use Inertia\Testing\AssertableInertia as Assert;

it("gives the booking page the week's opening hours, joined across the team, and the server's clock", function () {
    $this->travelTo('2026-10-20 09:15:00');
    $business = Business::factory()->create(['slug' => 'glow-studio']);
    app(CurrentBusiness::class)->set($business);
    $priya = Staff::factory()->for($business)->create();
    $sam = Staff::factory()->for($business)->create();
    $off = Staff::factory()->for($business)->create(['is_active' => false]);
    $service = Service::factory()->for($business)->create();
    $service->staff()->attach([$priya->id, $sam->id]);

    // Tuesday: 9 to 1 and 12 to 5 join up; a break before Sam's evening.
    app(SaveWeeklyHours::class)->handle($priya, [['weekday' => 2, 'starts_at' => '09:00', 'ends_at' => '13:00']]);
    app(SaveWeeklyHours::class)->handle($sam, [['weekday' => 2, 'starts_at' => '12:00', 'ends_at' => '17:00'], ['weekday' => 2, 'starts_at' => '18:00', 'ends_at' => '21:00']]);
    // Someone not taking bookings doesn't open the business.
    app(SaveWeeklyHours::class)->handle($off, [['weekday' => 7, 'starts_at' => '10:00', 'ends_at' => '14:00']]);
    app(CurrentBusiness::class)->set(null);

    $this->get('/glow-studio')->assertInertia(fn (Assert $page) => $page
        ->where('hours.2', [['09:00', '17:00'], ['18:00', '21:00']])
        ->where('hours.7', [])
        ->where('serverNow', '2026-10-20T09:15:00Z'));
});
