<?php

use App\Models\User;
use Illuminate\Support\Facades\Session;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->forceFill(['is_admin' => true])->save();
});

it('loads the page you were heading for in full, so the admin tools never open in a pop-up', function () {
    $this->get('/horizon')->assertRedirect(route('login'));

    $this->withHeaders(['X-Inertia' => 'true'])
        ->post(route('login.store'), ['email' => $this->admin->email, 'password' => 'password'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', url('/horizon'));

    $this->assertAuthenticatedAs($this->admin);
});

it('does the same after a two-factor code', function () {
    $user = User::factory()->withTwoFactor()->create();
    $user->forceFill(['is_admin' => true])->save();

    $this->get('/admin')->assertRedirect(route('login'));
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));

    $this->withHeaders(['X-Inertia' => 'true'])
        ->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', url('/admin'));
});

it('still redirects as usual outside Inertia', function () {
    $owner = User::factory()->create();

    $this->post(route('login.store'), ['email' => $owner->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    expect(Session::has('url.intended'))->toBeFalse();
});
