<?php

use App\Enums\BusinessRole;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

it('holds times for 15 minutes unless the business says otherwise', function () {
    expect((new Business)->hold_minutes)->toBe(15)
        ->and(Business::factory()->create()->fresh()->hold_minutes)->toBe(15);
});

function rulesForm(array $override = []): array
{
    return [
        'hold_minutes' => 20,
        'min_notice_minutes' => 240,
        'booking_horizon_days' => 90,
        'slot_step_minutes' => 30,
        'cancellation_window_hours' => 48,
        ...$override,
    ];
}

it('lets the owner see and change the booking rules', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->ownedBy($owner)->create();
    $other = Business::factory()->create();

    $this->actingAs($owner)
        ->get(route('business.rules.edit'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('business/BookingRules')
            ->where('rules.hold_minutes', 15)
            ->where('steps', [5, 10, 15, 20, 30, 60]));

    $this->put(route('business.rules.update'), rulesForm())
        ->assertRedirect(route('business.rules.edit'));

    expect($business->fresh()->only(array_keys(rulesForm())))->toBe(rulesForm())
        ->and($other->fresh()->hold_minutes)->toBe(15);
});

it('keeps the rules within sensible limits', function (array $override, string $field) {
    $owner = User::factory()->create();
    Business::factory()->ownedBy($owner)->create();

    $this->actingAs($owner)
        ->put(route('business.rules.update'), rulesForm($override))
        ->assertSessionHasErrors($field);
})->with([
    'hold too short' => [['hold_minutes' => 2], 'hold_minutes'],
    'hold too long' => [['hold_minutes' => 60], 'hold_minutes'],
    'odd slot step' => [['slot_step_minutes' => 25], 'slot_step_minutes'],
    'no horizon' => [['booking_horizon_days' => 0], 'booking_horizon_days'],
    'notice over a week' => [['min_notice_minutes' => 20000], 'min_notice_minutes'],
    'window over a week' => [['cancellation_window_hours' => 500], 'cancellation_window_hours'],
]);

it('is only for owners', function () {
    $business = Business::factory()->create();
    $staff = User::factory()->create();
    $business->addMember($staff, BusinessRole::Staff);

    $this->actingAs($staff)->get(route('business.rules.edit'))->assertForbidden();
    $this->actingAs($staff)->put(route('business.rules.update'), rulesForm())->assertForbidden();
});

it('uses the new hold time for the next hold', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00', 'UTC'));
    $business = Business::factory()->create(['slug' => 'glow-studio', 'hold_minutes' => 25, 'min_notice_minutes' => 0, 'slot_step_minutes' => 60]);
    $service = Service::factory()->for($business)->create(['duration_minutes' => 60]);
    $staff = Staff::factory()->for($business)->create();
    $service->staff()->attach($staff);
    $hour = new WorkingHour(['staff_id' => $staff->id, 'weekday' => 2, 'starts_at' => '09:00', 'ends_at' => '12:00']);
    $hour->business_id = $business->id;
    $hour->save();

    $this->post('/glow-studio/hold', ['service_id' => $service->id, 'starts_at' => '2026-10-27T09:00:00Z']);

    expect(Booking::sole()->hold_expires_at->toIso8601ZuluString())->toBe('2026-10-26T08:25:00Z');
});
