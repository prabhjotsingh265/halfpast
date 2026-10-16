<?php

use App\Models\Business;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->business = Business::factory()->ownedBy($this->owner)->create();
});

it('chooses who offers a service', function () {
    $priya = Staff::factory()->for($this->business)->create();
    $sam = Staff::factory()->for($this->business)->create();

    $this->actingAs($this->owner)
        ->post(route('services.store'), [
            'name' => 'Haircut',
            'duration_minutes' => 30,
            'buffer_after_minutes' => 0,
            'price' => '30',
            'deposit_type' => 'none',
            'staff_ids' => [$priya->id],
        ])
        ->assertRedirect(route('services.index'));

    $service = Service::firstOrFail();

    expect($service->staff->pluck('id')->all())->toBe([$priya->id]);

    // Unticking everyone sends no staff_ids at all.
    $this->put(route('services.update', $service), [
        'name' => 'Haircut',
        'duration_minutes' => 30,
        'buffer_after_minutes' => 0,
        'price' => '30',
        'deposit_type' => 'none',
    ])->assertRedirect(route('services.index'));

    expect($service->fresh()->staff)->toHaveCount(0);

    $this->get(route('services.edit', $service))
        ->assertInertia(fn (Assert $page) => $page
            ->has('staff', 2)
            ->where('service.staff_ids', []));

    expect($sam->services)->toHaveCount(0);
});

it('flags services no one offers', function () {
    Service::factory()->for($this->business)->create();

    $this->actingAs($this->owner)
        ->get(route('services.index'))
        ->assertInertia(fn (Assert $page) => $page->where('services.0.staff_count', 0));
});

it("won't give a service to another business's staff", function () {
    $theirs = Staff::factory()->for(Business::factory())->create();

    $this->actingAs($this->owner)
        ->post(route('services.store'), [
            'name' => 'Haircut',
            'duration_minutes' => 30,
            'buffer_after_minutes' => 0,
            'price' => '30',
            'deposit_type' => 'none',
            'staff_ids' => [$theirs->id],
        ])
        ->assertSessionHasErrors('staff_ids.0');
});
