<?php

use App\Models\Business;
use App\Models\User;
use App\Rules\BookingAddress;

it('shows the queue dashboard to platform admins only', function () {
    $this->get('/horizon')->assertRedirect(route('login'));

    $owner = User::factory()->create();
    Business::factory()->ownedBy($owner)->create();
    $this->actingAs($owner)->get('/horizon')->assertForbidden();
    $this->actingAs($owner)->getJson('/horizon/api/stats')->assertForbidden();

    $demo = User::factory()->create();
    $demo->forceFill(['is_demo' => true, 'is_admin' => true])->save();
    $this->actingAs($demo)->get('/horizon')->assertForbidden();

    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();
    $this->actingAs($admin)->get('/horizon')->assertOk()->assertSee('Horizon');
});

it('keeps framing protection on the dashboard, without the policy its scripts would trip', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    $this->actingAs($admin)->get('/horizon')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeaderMissing('Content-Security-Policy');
});

it("doesn't let a business take the dashboard's address", function () {
    expect(BookingAddress::reserved())->toContain('horizon');
});
