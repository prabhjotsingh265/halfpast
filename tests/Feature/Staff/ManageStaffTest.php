<?php

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Models\WorkingHour;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->business = Business::factory()->ownedBy($this->owner)->create();
});

function staffForm(array $override = []): array
{
    return [
        'name' => 'Priya',
        'bio' => 'Colour specialist.',
        'is_active' => true,
        'service_ids' => [],
        'hours' => [
            ['weekday' => 2, 'starts_at' => '09:00', 'ends_at' => '13:00'],
            ['weekday' => 2, 'starts_at' => '14:00', 'ends_at' => '18:00'],
            ['weekday' => 6, 'starts_at' => '10:00', 'ends_at' => '24:00'],
        ],
        ...$override,
    ];
}

it('lists the team with their hours and services', function () {
    $service = Service::factory()->for($this->business)->create(['name' => 'Haircut']);
    $member = Staff::factory()->for($this->business)->withOfficeHours()->create(['name' => 'Priya']);
    $member->services()->attach($service);

    $this->actingAs($this->owner)
        ->get(route('staff.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('staff/Index')
            ->has('staff', 1)
            ->where('staff.0.name', 'Priya')
            ->where('staff.0.services', ['Haircut'])
            ->has('staff.0.hours', 10)
            ->where('staff.0.hours.0', ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '13:00']));
});

it('adds someone with their services and weekly hours', function () {
    $haircut = Service::factory()->for($this->business)->create();
    $colour = Service::factory()->for($this->business)->create();

    $this->actingAs($this->owner)
        ->post(route('staff.store'), staffForm(['service_ids' => [$haircut->id, $colour->id]]))
        ->assertRedirect(route('staff.index'));

    $member = Staff::where('name', 'Priya')->firstOrFail();

    expect($member->business_id)->toBe($this->business->id)
        ->and($member->services->pluck('id')->sort()->values()->all())->toBe([$haircut->id, $colour->id])
        ->and($member->workingHours)->toHaveCount(3)
        ->and($member->workingHours->every(fn (WorkingHour $hour) => $hour->business_id === $this->business->id))->toBeTrue()
        ->and($member->workingHours->last()->ends_at)->toBe('24:00:00');
});

it('replaces the whole week when hours are saved again', function () {
    $member = Staff::factory()->for($this->business)->withOfficeHours()->create();

    $this->actingAs($this->owner)
        ->put(route('staff.update', $member), staffForm([
            'name' => 'Priya S',
            'hours' => [['weekday' => 7, 'starts_at' => '11:00', 'ends_at' => '15:30']],
        ]))
        ->assertRedirect(route('staff.index'));

    $member->refresh();

    expect($member->name)->toBe('Priya S')
        ->and($member->workingHours)->toHaveCount(1)
        ->and($member->workingHours->first()->weekday)->toBe(7)
        ->and($member->workingHours->first()->starts_at)->toBe('11:00:00');
});

it('takes someone off the team along with their hours', function () {
    $member = Staff::factory()->for($this->business)->withOfficeHours()->create();

    $this->actingAs($this->owner)
        ->delete(route('staff.destroy', $member))
        ->assertRedirect(route('staff.index'));

    expect(Staff::whereKey($member->id)->exists())->toBeFalse()
        ->and(WorkingHour::where('staff_id', $member->id)->count())->toBe(0);
});

it('rejects hours that do not make sense', function (array $hours, string $field) {
    $this->actingAs($this->owner)
        ->post(route('staff.store'), staffForm(['hours' => $hours]))
        ->assertSessionHasErrors($field);

    expect(Staff::where('name', 'Priya')->exists())->toBeFalse();
})->with([
    'ends before it starts' => [[['weekday' => 1, 'starts_at' => '17:00', 'ends_at' => '09:00']], 'hours.0.ends_at'],
    'overlapping stretches' => [[
        ['weekday' => 3, 'starts_at' => '09:00', 'ends_at' => '13:00'],
        ['weekday' => 3, 'starts_at' => '12:00', 'ends_at' => '15:00'],
    ], 'hours.1.starts_at'],
    'no such weekday' => [[['weekday' => 8, 'starts_at' => '09:00', 'ends_at' => '17:00']], 'hours.0.weekday'],
    'off the five-minute grid' => [[['weekday' => 1, 'starts_at' => '09:07', 'ends_at' => '17:00']], 'hours.0.starts_at'],
    'not a time' => [[['weekday' => 1, 'starts_at' => 'nine', 'ends_at' => '17:00']], 'hours.0.starts_at'],
    'past midnight' => [[['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '24:30']], 'hours.0.ends_at'],
]);

it('lets the same hours appear on different days', function () {
    $this->actingAs($this->owner)
        ->post(route('staff.store'), staffForm(['hours' => [
            ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00'],
            ['weekday' => 2, 'starts_at' => '09:00', 'ends_at' => '17:00'],
        ]]))
        ->assertSessionHasNoErrors();
});

it("won't link someone to another business's services", function () {
    $theirs = Service::factory()->for(Business::factory())->create();

    $this->actingAs($this->owner)
        ->post(route('staff.store'), staffForm(['service_ids' => [$theirs->id]]))
        ->assertSessionHasErrors('service_ids.0');
});

it("keeps other businesses' staff out of reach", function () {
    $theirs = Staff::factory()->for(Business::factory())->create();

    $this->actingAs($this->owner);

    $this->get(route('staff.edit', $theirs))->assertNotFound();
    $this->put(route('staff.update', $theirs), staffForm())->assertNotFound();
    $this->delete(route('staff.destroy', $theirs))->assertNotFound();

    $this->get(route('staff.index'))
        ->assertInertia(fn (Assert $page) => $page->has('staff', 0));
});

it('is only for owners', function () {
    $staffUser = User::factory()->create();
    $this->business->addMember($staffUser, BusinessRole::Staff);

    $this->actingAs($staffUser)
        ->get(route('staff.index'))
        ->assertForbidden();
});
