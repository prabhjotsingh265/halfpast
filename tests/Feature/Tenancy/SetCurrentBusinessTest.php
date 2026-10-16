<?php

use App\Enums\BusinessRole;
use App\Http\Middleware\SetCurrentBusiness;
use App\Models\Business;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it("shares the person's business with every page", function () {
    $owner = User::factory()->create();
    $business = Business::factory()->ownedBy($owner)->create(['name' => 'Glow Studio']);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('business.name', 'Glow Studio')
            ->where('business.slug', $business->slug)
            ->where('business.role', BusinessRole::Owner->value));
});

it('remembers which business the person switched to', function () {
    $user = User::factory()->create();
    Business::factory()->ownedBy($user)->create();
    $second = Business::factory()->create(['name' => 'Northside Physio']);
    $second->addMember($user, BusinessRole::Staff);

    $this->actingAs($user)
        ->withSession([SetCurrentBusiness::SESSION_KEY => $second->id])
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('business.name', 'Northside Physio')
            ->where('business.role', BusinessRole::Staff->value));
});

it("ignores a remembered business the person doesn't belong to", function () {
    $user = User::factory()->create();
    $own = Business::factory()->ownedBy($user)->create();
    $someoneElses = Business::factory()->ownedBy()->create();

    $this->actingAs($user)
        ->withSession([SetCurrentBusiness::SESSION_KEY => $someoneElses->id])
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('business.id', $own->id));
});

it('shares no business with guests', function () {
    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('business', null));
});
