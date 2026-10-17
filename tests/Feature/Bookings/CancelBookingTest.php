<?php

use App\Actions\Booking\CancelBooking;
use App\Actions\Booking\CancellationTerms;
use App\Enums\BookingStatus;
use App\Mail\BookingCancelledMail;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Staff;
use App\Tenancy\CurrentBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A colour at Glow Studio on Friday 30 October 2026 at 10:00 London time,
 * with a £20 deposit paid. Free cancellation ends 24 hours before.
 */

beforeEach(function () {
    $this->business = Business::factory()->create(['slug' => 'glow-studio', 'timezone' => 'Europe/London', 'cancellation_window_hours' => 24]);
    app(CurrentBusiness::class)->set($this->business);

    $this->booking = Booking::factory()->for($this->business)
        ->at('2026-10-30 10:00', '2026-10-30 11:00')
        ->create([
            'staff_id' => Staff::factory()->for($this->business)->create()->id,
            'customer_id' => Customer::factory()->for($this->business)->create(['email' => 'ananya@example.com'])->id,
            'service_name' => 'Colour',
            'price' => 8000,
            'deposit' => 2000,
            'currency' => 'GBP',
        ]);
});

it('works out what cancelling would mean', function (string $now, bool $allowed, int $refund, int $kept) {
    $terms = CancellationTerms::for($this->booking, $this->business, CarbonImmutable::parse($now));

    expect($terms->allowed)->toBe($allowed)
        ->and($terms->refund)->toBe($refund)
        ->and($terms->kept)->toBe($kept)
        ->and($terms->freeUntil->toIso8601ZuluString())->toBe('2026-10-29T10:00:00Z');
})->with([
    'days ahead' => ['2026-10-26 09:00', true, 2000, 0],
    'right at the window' => ['2026-10-29 10:00', true, 2000, 0],
    'inside the window' => ['2026-10-29 10:01', true, 0, 2000],
    'after it started' => ['2026-10-30 10:05', false, 0, 0],
]);

it('lets the customer cancel from their booking page, refunding the deposit in time', function () {
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-26 09:00', 'UTC'));

    $this->get($this->booking->manageUrl())
        ->assertInertia(fn (Assert $page) => $page
            ->where('cancellation.refund', 2000)
            ->where('cancellation.kept', 0)
            ->where('cancellation.window_hours', 24));

    $page = $this->get($this->booking->manageUrl())->viewData('page');
    $cancelUrl = $page['props']['cancellation']['url'];

    $this->post($cancelUrl)->assertRedirect($this->booking->manageUrl());

    $booking = $this->booking->fresh();

    expect($booking->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->cancelled_by)->toBe('customer')
        ->and($booking->refund_due)->toBe(2000)
        ->and($booking->cancelled_at->toIso8601ZuluString())->toBe('2026-10-26T09:00:00Z');

    Mail::assertQueued(BookingCancelledMail::class, fn ($mail) => $mail->hasTo('ananya@example.com'));

    $this->get($booking->manageUrl())
        ->assertInertia(fn (Assert $page) => $page
            ->where('booking.status', 'cancelled')
            ->where('booking.refund_due', 2000)
            ->where('cancellation', null));
});

it('keeps the deposit inside the window', function () {
    app(CancelBooking::class)->handle($this->booking, 'customer', now: CarbonImmutable::parse('2026-10-30 08:00', 'UTC'));

    expect($this->booking->fresh()->refund_due)->toBe(0)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::Cancelled);
});

it('lets the business refund in full whenever it cancels', function () {
    app(CancelBooking::class)->handle($this->booking, 'business', refundInFull: true, now: CarbonImmutable::parse('2026-10-30 08:00', 'UTC'));

    expect($this->booking->fresh()->refund_due)->toBe(2000)
        ->and($this->booking->fresh()->cancelled_by)->toBe('business');
});

it('frees the time for someone else', function () {
    app(CancelBooking::class)->handle($this->booking, 'customer', now: CarbonImmutable::parse('2026-10-26 09:00', 'UTC'));

    Booking::factory()->for($this->business)->at('2026-10-30 10:00', '2026-10-30 11:00')->create(['staff_id' => $this->booking->staff_id]);

    expect(Booking::where('status', BookingStatus::Confirmed)->count())->toBe(1);
});

it('cancels once, and never after the appointment has started', function () {
    $now = CarbonImmutable::parse('2026-10-26 09:00', 'UTC');

    expect(app(CancelBooking::class)->handle($this->booking, 'customer', now: $now))->toBeTrue()
        ->and(app(CancelBooking::class)->handle($this->booking, 'customer', now: $now))->toBeFalse();

    $later = Booking::factory()->for($this->business)->at('2026-10-20 10:00', '2026-10-20 11:00')->create(['staff_id' => $this->booking->staff_id]);

    expect(app(CancelBooking::class)->handle($later, 'customer', now: $now))->toBeFalse()
        ->and($later->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it('only cancels through the signed link', function () {
    $this->post("/glow-studio/booking/{$this->booking->code}/cancel")->assertForbidden();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it('writes a cancellation email that says what happens to the deposit', function () {
    app(CancelBooking::class)->handle($this->booking, 'customer', now: CarbonImmutable::parse('2026-10-26 09:00', 'UTC'));

    (new BookingCancelledMail($this->booking->fresh()))
        ->assertSeeInHtml('Your booking is cancelled.')
        ->assertSeeInHtml('£20 deposit is being refunded')
        ->assertSeeInText('Book another time:');
});
