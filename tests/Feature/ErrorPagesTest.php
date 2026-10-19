<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config(['app.debug' => false]);

    Route::middleware('web')->get('/_status/{code}', fn (int $code) => abort($code, '', $code === 429 ? ['Retry-After' => 30] : []));
});

it('tells someone with a wrong booking address what happened', function () {
    $this->get('/glow-stuido')
        ->assertNotFound()
        ->assertSee('Nothing is booked at this address.')
        ->assertSee('<code>/glow-stuido</code>', false);

    $this->get('/somewhere/deeper/still')
        ->assertNotFound()
        ->assertSee("This page isn't here.");
});

it('turns away someone without access, and offers their own dashboard', function () {
    $owner = User::factory()->create();
    Business::factory()->ownedBy($owner)->create();

    $this->actingAs($owner)->get('/admin')
        ->assertForbidden()
        ->assertSee('This time is blocked out for you.')
        ->assertSee(route('dashboard'), false);
});

it('explains every other status in plain words', function (int $code, string $title, string $more) {
    $this->get("/_status/{$code}")
        ->assertStatus($code)
        ->assertSee($title)
        ->assertSee($more);
})->with([
    'expired form' => [419, 'The page sat open a little too long.', 'Go back'],
    'too many tries' => [429, 'That was a lot of tries at once.', 'Give it 30 seconds'],
    'server error' => [500, 'Something broke on our side.', 'check your email first'],
    'maintenance' => [503, 'Back in a few minutes.', 'Bookings already made are safe'],
    'any other 4xx' => [410, "That didn't work.", 'Check the link'],
    'any other 5xx' => [502, 'Something broke on our side.', 'Try again'],
]);

it('stands on its own, with no app scripts or built files to fail', function () {
    $html = $this->get('/_status/500')->getContent();

    expect($html)->not->toContain('<script')
        ->not->toContain('/build/')
        ->toContain('conic-gradient(oklch(')
        ->toContain('prefers-reduced-motion');
});
