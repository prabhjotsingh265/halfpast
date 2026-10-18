<?php

use App\Models\Business;
use App\Models\User;

it('sends a content security policy with a fresh nonce for the inline scripts', function () {
    $response = $this->get('/login')->assertOk();

    $policy = (string) $response->headers->get('Content-Security-Policy');
    preg_match("/'nonce-([^']+)'/", $policy, $nonce);

    expect($policy)->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->toContain('https://checkout.razorpay.com')
        ->and($nonce[1] ?? null)->not->toBeNull()
        // The dark mode check and the route list both carry it.
        ->and(substr_count($response->getContent(), 'nonce="'.$nonce[1].'"'))->toBeGreaterThanOrEqual(2);

    $again = $this->get('/login');
    expect($again->headers->get('Content-Security-Policy'))->not->toBe($policy);
});

it('refuses framing and content sniffing everywhere', function () {
    Business::factory()->create(['slug' => 'glow-studio']);

    $this->get('/glow-studio')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('puts the policy on pages only, not on data', function () {
    $owner = User::factory()->create();
    Business::factory()->ownedBy($owner)->create();

    $this->actingAs($owner)
        ->getJson(route('customers.search', ['search' => 'an']))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeaderMissing('Content-Security-Policy');
});

it('leaves the admin to its own rules', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    $this->actingAs($admin)->get('/admin')->assertOk()->assertHeaderMissing('Content-Security-Policy');
});

it('remembers HTTPS only in production, and the policy can be switched off', function () {
    $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');

    $this->app['env'] = 'production';

    try {
        $this->get('/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    } finally {
        $this->app['env'] = 'testing';
    }

    config(['security.csp' => false]);
    $this->get('/login')->assertHeaderMissing('Content-Security-Policy');
});
