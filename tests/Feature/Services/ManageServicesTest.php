<?php

use App\Enums\BusinessRole;
use App\Enums\DepositType;
use App\Models\Business;
use App\Models\Service;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->business = Business::factory()->ownedBy($this->owner)->create(['currency' => 'INR']);
});

function serviceForm(array $override = []): array
{
    return [
        'name' => 'Haircut',
        'description' => 'Wash, cut and finish.',
        'duration_minutes' => 30,
        'buffer_after_minutes' => 10,
        'price' => '1200',
        'deposit_type' => 'percent',
        'deposit_value' => '20',
        'is_active' => true,
        ...$override,
    ];
}

it("lists the business's services with their deposits", function () {
    Service::factory()->for($this->business)->withPercentDeposit(20)->create(['name' => 'Haircut', 'price' => 120000]);

    $this->actingAs($this->owner)
        ->get(route('services.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('services/Index')
            ->where('currency', 'INR')
            ->has('services', 1)
            ->where('services.0.name', 'Haircut')
            ->where('services.0.deposit', 24000));
});

it('adds a service, storing money in minor units', function () {
    $this->actingAs($this->owner)
        ->post(route('services.store'), serviceForm(['price' => '1200.50', 'deposit_type' => 'fixed', 'deposit_value' => '250']))
        ->assertRedirect(route('services.index'));

    $service = Service::firstOrFail();

    expect($service->business_id)->toBe($this->business->id)
        ->and($service->price)->toBe(120050)
        ->and($service->deposit_type)->toBe(DepositType::Fixed)
        ->and($service->deposit_value)->toBe(25000)
        ->and($service->buffer_after_minutes)->toBe(10);
});

it('updates and deletes a service', function () {
    $service = Service::factory()->for($this->business)->create();

    $this->actingAs($this->owner)
        ->put(route('services.update', $service), serviceForm(['name' => 'Cut and finish', 'deposit_type' => 'none', 'deposit_value' => null]))
        ->assertRedirect(route('services.index'));

    expect($service->fresh()->name)->toBe('Cut and finish')
        ->and($service->fresh()->depositAmount())->toBe(0);

    $this->delete(route('services.destroy', $service))->assertRedirect(route('services.index'));

    expect(Service::count())->toBe(0);
});

it('rejects services that do not add up', function (array $override, string $field) {
    $this->actingAs($this->owner)
        ->post(route('services.store'), serviceForm($override))
        ->assertSessionHasErrors($field);
})->with([
    'no name' => [['name' => ''], 'name'],
    'odd duration' => [['duration_minutes' => 32], 'duration_minutes'],
    'too short' => [['duration_minutes' => 0], 'duration_minutes'],
    'negative price' => [['price' => '-5'], 'price'],
    'percent over 100' => [['deposit_value' => '120'], 'deposit_value'],
    'fixed deposit over price' => [['deposit_type' => 'fixed', 'deposit_value' => '5000'], 'deposit_value'],
    'missing deposit amount' => [['deposit_type' => 'fixed', 'deposit_value' => null], 'deposit_value'],
    'unknown deposit type' => [['deposit_type' => 'tip'], 'deposit_type'],
]);

it('keeps staff out of service settings', function () {
    $stylist = User::factory()->create();
    $this->business->addMember($stylist, BusinessRole::Staff);

    $this->actingAs($stylist)->get(route('services.index'))->assertForbidden();
    $this->actingAs($stylist)->post(route('services.store'), serviceForm())->assertForbidden();
});

it("never shows or changes another business's services", function () {
    $theirs = Service::factory()->for(Business::factory()->ownedBy())->create(['name' => 'Their service']);

    $this->actingAs($this->owner);

    $this->get(route('services.index'))
        ->assertInertia(fn (Assert $page) => $page->has('services', 0));
    $this->get(route('services.edit', $theirs))->assertNotFound();
    $this->put(route('services.update', $theirs), serviceForm())->assertNotFound();
    $this->delete(route('services.destroy', $theirs))->assertNotFound();

    expect($theirs->fresh()->name)->toBe('Their service');
});
