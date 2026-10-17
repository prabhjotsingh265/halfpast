<?php

use App\Enums\BookingStatus;
use App\Enums\BusinessRole;
use App\Models\Booking;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

it('seeds a demo salon that can be logged into and booked', function () {
    $this->seed(DemoSeeder::class);

    $owner = User::where('email', 'demo@halfpast.test')->sole();
    $business = Business::where('slug', 'kesar-beauty-lounge')->sole();

    expect($owner->roleIn($business))->toBe(BusinessRole::Owner)
        ->and(Booking::withoutGlobalScopes()->where('status', BookingStatus::Confirmed)->count())->toBeGreaterThan(0)
        ->and(Booking::withoutGlobalScopes()->whereNull('customer_id')->count())->toBe(0);

    $this->get('/kesar-beauty-lounge')
        ->assertInertia(fn (Assert $page) => $page->has('services', 6)->has('staff', 3));

    $this->actingAs($owner)->get(route('staff.index'))->assertOk();
});
