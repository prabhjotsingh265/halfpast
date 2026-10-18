<?php

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->business = Business::factory()->ownedBy($this->owner)->create(['slug' => 'glow-studio', 'currency' => 'GBP', 'payment_gateways' => []]);
    $staff = Staff::factory()->for($this->business)->create();
    $this->trim = Service::factory()->for($this->business)->create(['name' => 'Trim']);
    $this->colour = Service::factory()->for($this->business)->withFixedDeposit(2000)->create(['name' => 'Colour']);
    $this->trim->staff()->attach($staff);
    $this->colour->staff()->attach($staff);
});

it('shows the owner every gateway and whether it can be switched on', function () {
    $this->actingAs($this->owner)
        ->get(route('business.payments.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('business/PaymentMethods')
            ->where('currency', 'GBP')
            ->where('depositServices', 1)
            ->where('gateways', fn ($gateways) => collect($gateways)->keyBy('key')->map(fn ($g) => [$g['configured'], $g['supportsCurrency'], $g['upcoming']])->all() === [
                'stripe' => [false, true, false],
                'razorpay' => [false, false, false],
                'paypal' => [false, true, true],
                'test' => [true, true, false],
            ]));
});

it('switches payment methods on and off', function () {
    $this->actingAs($this->owner)
        ->put(route('business.payments.update'), ['gateways' => ['test']])
        ->assertRedirect(route('business.payments.edit'));

    expect($this->business->fresh()->payment_gateways)->toBe(['test']);

    $this->put(route('business.payments.update'), ['gateways' => []]);

    expect($this->business->fresh()->payment_gateways)->toBe([]);
});

it("won't switch on a gateway that isn't set up or doesn't exist", function (string $gateway) {
    $this->actingAs($this->owner)
        ->put(route('business.payments.update'), ['gateways' => [$gateway]])
        ->assertSessionHasErrors('gateways.0');

    expect($this->business->fresh()->payment_gateways)->toBe([]);
})->with(['stripe', 'paypal', 'bitcoin']);

it('only offers services with a deposit once customers can pay it', function () {
    $this->get('/glow-studio')->assertInertia(fn (Assert $page) => $page->has('services', 1)->where('services.0.name', 'Trim'));

    $this->post('/glow-studio/hold', ['service_id' => $this->colour->id, 'starts_at' => now()->addDay()->toIso8601ZuluString()])
        ->assertSessionHasErrors('service_id');

    $this->business->update(['payment_gateways' => ['test']]);

    $this->get('/glow-studio')->assertInertia(fn (Assert $page) => $page->has('services', 2));
});

it('tells the owner which services need a payment method', function () {
    $this->actingAs($this->owner)
        ->get(route('services.index'))
        ->assertInertia(fn (Assert $page) => $page->where('canTakeDeposits', false));

    $this->business->update(['payment_gateways' => ['test']]);

    $this->get(route('services.index'))->assertInertia(fn (Assert $page) => $page->where('canTakeDeposits', true));
});

it('is only for owners', function () {
    $staffUser = User::factory()->create();
    $this->business->addMember($staffUser, BusinessRole::Staff);

    $this->actingAs($staffUser)->get(route('business.payments.edit'))->assertForbidden();
    $this->actingAs($staffUser)->put(route('business.payments.update'), ['gateways' => ['test']])->assertForbidden();
});
