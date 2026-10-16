<?php

use App\Enums\BusinessRole;
use App\Http\Controllers\OnboardingController;
use App\Models\Business;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('sends people without a business to set one up', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding.create'));
});

it('remembers the name typed on the home page for the setup step', function () {
    $this->get(route('register', ['business' => 'Glow Studio']))->assertOk();

    $this->actingAs(User::factory()->create())
        ->get(route('onboarding.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('onboarding/CreateBusiness')
            ->where('suggestedName', 'Glow Studio'));
});

it('creates the business with the person as its owner', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession([OnboardingController::SUGGESTED_NAME => 'Glow Studio'])
        ->post(route('onboarding.store'), [
            'name' => 'Glow Studio',
            'slug' => 'glow-studio',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionMissing(OnboardingController::SUGGESTED_NAME);

    $business = Business::where('slug', 'glow-studio')->firstOrFail();

    expect($business->timezone)->toBe('Asia/Kolkata')
        ->and($business->currency)->toBe('INR')
        ->and($user->roleIn($business))->toBe(BusinessRole::Owner);

    $this->get(route('dashboard'))->assertOk();
});

it('sends people who already have a business straight to the dashboard', function () {
    $owner = User::factory()->create();
    Business::factory()->ownedBy($owner)->create();

    $this->actingAs($owner)
        ->get(route('onboarding.create'))
        ->assertRedirect(route('dashboard'));
});

it('rejects bad details', function (array $override, string $field) {
    Business::factory()->create(['slug' => 'taken-name']);

    $this->actingAs(User::factory()->create())
        ->post(route('onboarding.store'), [
            'name' => 'Glow Studio',
            'slug' => 'glow-studio',
            'timezone' => 'Europe/London',
            'currency' => 'GBP',
            ...$override,
        ])
        ->assertSessionHasErrors($field);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'reserved address' => [['slug' => 'login'], 'slug'],
    'taken address' => [['slug' => 'taken-name'], 'slug'],
    'badly formed address' => [['slug' => 'Glow Studio'], 'slug'],
    'unknown time zone' => [['timezone' => 'Mars/Olympus'], 'timezone'],
    'unsupported currency' => [['currency' => 'XYZ'], 'currency'],
]);

it('checks a booking address while it is typed', function (string $slug, bool $available) {
    Business::factory()->create(['slug' => 'taken-name']);

    $this->actingAs(User::factory()->create())
        ->getJson(route('onboarding.address', ['slug' => $slug]))
        ->assertOk()
        ->assertJson(['available' => $available]);
})->with([
    'free' => ['glow-studio', true],
    'taken' => ['taken-name', false],
    'reserved' => ['dashboard', false],
    'badly formed' => ['-nope', false],
]);

it('saves a time zone by its current name, whatever the browser called it', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('onboarding.store'), [
            'name' => 'Kesar Beauty Lounge',
            'slug' => 'kesar-beauty-lounge',
            'timezone' => 'Asia/Calcutta',
            'currency' => 'INR',
        ])
        ->assertRedirect(route('dashboard'));

    expect(Business::where('slug', 'kesar-beauty-lounge')->value('timezone'))->toBe('Asia/Kolkata');
});
