<?php

use App\Models\Business;
use App\Models\User;
use App\Rules\BookingAddress;

dataset('tools', [
    'queues (Horizon)' => ['/horizon', 'Horizon'],
    'performance (Pulse)' => ['/pulse', 'Laravel Pulse'],
]);

it('shows platform admins only', function (string $path, string $heading) {
    $this->get($path)->assertRedirect(route('login'));

    $owner = User::factory()->create();
    Business::factory()->ownedBy($owner)->create();
    $this->actingAs($owner)->get($path)->assertForbidden();

    $demo = User::factory()->create();
    $demo->forceFill(['is_demo' => true, 'is_admin' => true])->save();
    $this->actingAs($demo)->get($path)->assertForbidden();

    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();
    $this->actingAs($admin)->get($path)->assertOk()->assertSee($heading);
})->with('tools');

it("keeps Horizon's numbers from anyone else", function () {
    $owner = User::factory()->create();
    Business::factory()->ownedBy($owner)->create();

    $this->actingAs($owner)->getJson('/horizon/api/stats')->assertForbidden();
});

it('keeps framing protection, without the policy their scripts would trip', function (string $path) {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    $this->actingAs($admin)->get($path)
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeaderMissing('Content-Security-Policy');
})->with('tools');

it("doesn't let a business take their addresses", function (string $path) {
    expect(BookingAddress::reserved())->toContain(ltrim($path, '/'));
})->with('tools');
