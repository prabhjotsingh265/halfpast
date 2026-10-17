<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Business;
use Illuminate\Console\Scheduling\Schedule;

it('releases holds that ran out, in every business, and nothing else', function () {
    $glow = Business::factory()->create();
    $kesar = Business::factory()->create();

    $lapsed = Booking::factory()->for($glow)->held()->create(['hold_expires_at' => now()->subMinute()]);
    $alsoLapsed = Booking::factory()->for($kesar)->held()->at('2026-11-02 10:00', '2026-11-02 10:30')->create(['hold_expires_at' => now()->subSecond()]);
    $live = Booking::factory()->for($glow)->held(5)->at('2026-11-02 11:00', '2026-11-02 11:30')->create();
    $confirmed = Booking::factory()->for($glow)->at('2026-11-02 12:00', '2026-11-02 12:30')->create();

    $this->artisan('bookings:expire-holds')
        ->expectsOutput('Released 2 lapsed holds.')
        ->assertSuccessful();

    expect($lapsed->fresh()->status)->toBe(BookingStatus::Expired)
        ->and($alsoLapsed->fresh()->status)->toBe(BookingStatus::Expired)
        ->and($live->fresh()->status)->toBe(BookingStatus::Held)
        ->and($confirmed->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it('runs every minute', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('bookings:expire-holds')->assertSuccessful();

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'bookings:expire-holds'));

    expect($event->expression)->toBe('* * * * *');
});
