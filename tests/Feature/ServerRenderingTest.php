<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as SsrRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['inertia.ssr.enabled' => true, 'inertia.ssr.ensure_bundle_exists' => false]);
    Business::factory()->create(['name' => 'Glow Studio', 'slug' => 'glow-studio']);
});

/** A renderer that answers every page with the component's name. */
function fakeRenderer(): void
{
    Http::fake(fn (SsrRequest $request) => Http::response([
        'head' => ['<meta name="rendered-by" content="server">'],
        'body' => '<div id="app" data-server-rendered="true">Rendered: '.$request['component'].'</div>',
    ]));
}

it('renders booking pages on the server, with the routes their links need', function () {
    fakeRenderer();

    $this->get('/glow-studio')
        ->assertOk()
        ->assertSee('Rendered: booking/Show', false)
        ->assertSee('<meta name="rendered-by" content="server">', false);

    Http::assertSent(fn (SsrRequest $request) => $request['component'] === 'booking/Show'
        && isset($request['props']['ziggy']['routes']['booking.hold']));
});

it('leaves the dashboard and the home page to the browser, without the route list', function () {
    fakeRenderer();

    $owner = User::factory()->create();
    Business::factory()->ownedBy($owner)->create();

    $dashboard = $this->actingAs($owner)->get(route('dashboard'))->assertOk();
    $home = $this->get('/')->assertOk();

    Http::assertNothingSent();
    expect($dashboard->getContent())->not->toContain('&quot;ziggy&quot;')
        ->and($home->getContent())->not->toContain('&quot;ziggy&quot;');
});

it("doesn't send the route list with visits between pages, which the browser renders", function () {
    fakeRenderer();

    $version = app(HandleInertiaRequests::class)->version(Request::create('/'));

    $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version])
        ->get('/glow-studio')
        ->assertOk()
        ->assertJsonMissingPath('props.ziggy');

    Http::assertNothingSent();
});

it('falls back to the browser when the renderer is down', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $this->get('/glow-studio')
        ->assertOk()
        ->assertSee('booking/Show')
        ->assertDontSee('data-server-rendered', false);
});

it('can be switched off', function () {
    fakeRenderer();
    config(['inertia.ssr.enabled' => false]);

    $this->get('/glow-studio')->assertOk()->assertDontSee('Rendered:', false);
    Http::assertNothingSent();
});
