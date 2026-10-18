<?php

use App\Enums\BookingStatus;
use App\Mail\BookingReminderMail;
use App\Models\Booking;
use App\Models\BookingReminder;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;

/*
 * It's Monday 26 October 2026, 08:00 UTC. Bookings were made days ago
 * unless a test says otherwise.
 */

beforeEach(function () {
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00', 'UTC'));
    $this->business = Business::factory()->create(['name' => 'Glow Studio', 'slug' => 'glow-studio', 'timezone' => 'Europe/London']);
});

function bookingStartingAt(string $start, array $override = [], ?Business $business = null): Booking
{
    $business ??= test()->business;
    $startsAt = CarbonImmutable::parse($start, 'UTC');

    $booking = Booking::factory()->for($business)->create([
        'staff_id' => Staff::factory()->for($business)->create(['name' => 'Priya'])->id,
        // One customer per email address per business, as the app keeps them.
        'customer_id' => (Customer::withoutGlobalScopes()->where('business_id', $business->id)->where('email', 'ananya@example.com')->first()
            ?? Customer::factory()->for($business)->create(['email' => 'ananya@example.com']))->id,
        'service_name' => 'Haircut',
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addMinutes(45),
        'blocked_until' => $startsAt->addMinutes(45),
        'price' => 3000,
        'deposit' => 600,
        ...$override,
    ]);

    // Made well ahead, unless the test says when.
    $booking->forceFill(['created_at' => $override['created_at'] ?? now()->subDays(3)])->save();

    return $booking;
}

it('reminds a day before, and only once however often it runs', function () {
    $booking = bookingStartingAt('2026-10-27 07:45');

    $this->artisan('bookings:send-reminders')->expectsOutput('Sent 1 reminder.')->assertSuccessful();
    $this->artisan('bookings:send-reminders')->expectsOutput('Sent 0 reminders.');

    Mail::assertQueued(BookingReminderMail::class, fn ($mail) => $mail->hasTo('ananya@example.com') && $mail->kind === '24h');
    Mail::assertQueuedCount(1);
    expect(BookingReminder::where('booking_id', $booking->id)->pluck('kind')->all())->toBe(['24h']);
});

it('reminds again two hours before', function () {
    $booking = bookingStartingAt('2026-10-26 09:30');

    $this->artisan('bookings:send-reminders');

    Mail::assertQueued(BookingReminderMail::class, fn ($mail) => $mail->kind === '2h');
    expect(BookingReminder::where('booking_id', $booking->id)->pluck('kind')->all())->toBe(['2h']);
});

it('waits until a reminder is due', function () {
    bookingStartingAt('2026-10-28 10:00');
    bookingStartingAt('2026-10-26 13:00');

    $this->artisan('bookings:send-reminders');

    Mail::assertNothingQueued();
});

it('only reminds confirmed bookings', function (string $status) {
    bookingStartingAt('2026-10-27 07:45', ['status' => BookingStatus::from($status)]);

    $this->artisan('bookings:send-reminders');

    Mail::assertNothingQueued();
})->with(['held', 'cancelled', 'expired']);

it('skips a reminder the customer would get right after booking', function () {
    // Booked half an hour ago for tomorrow: they've just had their confirmation.
    bookingStartingAt('2026-10-27 07:45', ['created_at' => now()->subMinutes(30)]);
    // Booked an hour ago for two hours' time: the same.
    bookingStartingAt('2026-10-26 09:30', ['created_at' => now()->subHour()]);

    $this->artisan('bookings:send-reminders');

    Mail::assertNothingQueued();
});

it('skips customers with no email address', function () {
    $booking = bookingStartingAt('2026-10-27 07:45');
    Customer::withoutGlobalScopes()->whereKey($booking->customer_id)->update(['email' => null]);

    $this->artisan('bookings:send-reminders');

    Mail::assertNothingQueued();
});

it('reminds customers of every business', function () {
    bookingStartingAt('2026-10-27 07:45');
    bookingStartingAt('2026-10-27 07:30', business: Business::factory()->create());

    $this->artisan('bookings:send-reminders')->expectsOutput('Sent 2 reminders.');
});

it('writes the reminder in the business time, with the link to view or cancel', function () {
    $booking = bookingStartingAt('2026-10-27 07:45');

    (new BookingReminderMail($booking, '24h'))
        ->assertHasSubject('Tomorrow: Haircut at 7:45 am')
        ->assertSeeInHtml('See you tomorrow.')
        ->assertSeeInHtml('with Priya')
        ->assertSeeInHtml('London time')
        ->assertSeeInHtml('£24')
        ->assertSeeInText('View or cancel:')
        ->assertSeeInText('/glow-studio/booking/'.$booking->code.'?signature=');

    (new BookingReminderMail($booking, '2h'))->assertHasSubject('In 2 hours: Haircut at 7:45 am');
});

it('runs every minute', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'bookings:send-reminders'));

    expect($event?->expression)->toBe('* * * * *');
});
