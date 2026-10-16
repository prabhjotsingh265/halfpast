<?php

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;

it('gives a business owners and staff', function () {
    $owner = User::factory()->create();
    $stylist = User::factory()->create();

    $business = Business::factory()->ownedBy($owner)->create();
    $business->addMember($stylist, BusinessRole::Staff);

    expect($business->members)->toHaveCount(2)
        ->and($business->owners->pluck('id')->all())->toBe([$owner->id])
        ->and($owner->roleIn($business))->toBe(BusinessRole::Owner)
        ->and($stylist->roleIn($business))->toBe(BusinessRole::Staff);
});

it('lists the businesses a person works at', function () {
    $user = User::factory()->create();
    $first = Business::factory()->ownedBy($user)->create();
    $second = Business::factory()->create();
    $second->addMember($user, BusinessRole::Staff);

    expect($user->businesses->pluck('id')->sort()->values()->all())
        ->toBe(collect([$first->id, $second->id])->sort()->values()->all());
});

it('has no role in a business the person does not work at', function () {
    $business = Business::factory()->ownedBy()->create();

    expect(User::factory()->create()->roleIn($business))->toBeNull();
});

it('keeps one membership per person per business', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create();

    $business->addMember($user, BusinessRole::Staff);
    $business->addMember($user, BusinessRole::Staff);

    expect($business->members()->count())->toBe(1);
});
