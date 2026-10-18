<?php

use App\Filament\Widgets\PlatformOverview;
use App\Models\User;
use App\Support\Heartbeat;
use Illuminate\Console\Scheduling\Schedule;
use Livewire\Livewire;

it('reports healthy when the database and cache answer', function () {
    $this->get('/up')->assertOk();
});

it('fails the health check in production once the scheduler has stopped', function () {
    $this->app['env'] = 'production';

    try {
        $this->get('/up')->assertServerError();

        Heartbeat::beat();
        $this->get('/up')->assertOk();

        $this->travel(Heartbeat::STALE_AFTER_MINUTES + 1)->minutes();
        $this->get('/up')->assertServerError();
    } finally {
        $this->app['env'] = 'testing';
    }
});

it('checks in every minute', function () {
    $beat = collect(app(Schedule::class)->events())->first(fn ($event) => $event->description === 'heartbeat');

    expect($beat)->not->toBeNull()->and($beat->expression)->toBe('* * * * *');

    expect(Heartbeat::last())->toBeNull();
    Heartbeat::beat();
    expect(Heartbeat::healthy())->toBeTrue()
        ->and(Heartbeat::last()?->diffInSeconds(now()))->toBeLessThan(2);
});

it('shows the scheduler on the admin front page', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();
    $this->actingAs($admin);

    Livewire::test(PlatformOverview::class)->assertSee('Scheduler')->assertSee('Never run');

    Heartbeat::beat();
    Livewire::test(PlatformOverview::class)->assertSee('running every minute');
});
