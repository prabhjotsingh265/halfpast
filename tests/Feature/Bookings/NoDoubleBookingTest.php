<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Staff;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * These go straight at the database, past any checks in the app, to show
 * the overlap rule holds on its own.
 */

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->priya = Staff::factory()->for($this->business)->create();
});

function bookPriya(string $start, string $end, string $status = 'confirmed', ?string $blockedUntil = null): Booking
{
    $factory = Booking::factory()
        ->for(test()->business)
        ->at($start, $end, $blockedUntil)
        ->state(['staff_id' => test()->priya->id, 'status' => BookingStatus::from($status)]);

    return ($status === 'held' ? $factory->held() : $factory)->create();
}

/** A refused insert aborts its transaction, so it runs inside its own (a savepoint). */
function overlapRefused(callable $insert): bool
{
    try {
        DB::transaction($insert);
    } catch (QueryException $e) {
        return $e->getCode() === Booking::OVERLAP_ERROR;
    }

    return false;
}

it('refuses a second booking that overlaps the first for the same person', function (string $status) {
    bookPriya('2026-11-02 10:00', '2026-11-02 11:00');

    expect(overlapRefused(fn () => bookPriya('2026-11-02 10:30', '2026-11-02 11:30', $status)))->toBeTrue()
        ->and(Booking::count())->toBe(1);
})->with(['held', 'confirmed']);

it('counts the free time after a booking as taken', function () {
    bookPriya('2026-11-02 10:00', '2026-11-02 10:30', blockedUntil: '2026-11-02 10:45');

    expect(overlapRefused(fn () => bookPriya('2026-11-02 10:40', '2026-11-02 11:10')))->toBeTrue();
});

it('lets bookings touch end to start', function () {
    bookPriya('2026-11-02 10:00', '2026-11-02 10:30');
    bookPriya('2026-11-02 10:30', '2026-11-02 11:00');

    expect(Booking::count())->toBe(2);
});

it('lets two people be booked at the same time', function () {
    bookPriya('2026-11-02 10:00', '2026-11-02 11:00');

    Booking::factory()->for($this->business)->at('2026-11-02 10:00', '2026-11-02 11:00')
        ->state(['staff_id' => Staff::factory()->for($this->business)])
        ->create();

    expect(Booking::count())->toBe(2);
});

it('frees the time once a booking is cancelled or a hold expires', function (string $status) {
    bookPriya('2026-11-02 10:00', '2026-11-02 11:00', $status);
    bookPriya('2026-11-02 10:00', '2026-11-02 11:00');

    expect(Booking::count())->toBe(2);
})->with(['cancelled', 'expired']);

it('gives every booking its own code for the manage link', function () {
    $first = bookPriya('2026-11-02 10:00', '2026-11-02 10:30');
    $second = bookPriya('2026-11-02 11:00', '2026-11-02 11:30');

    expect($first->code)->toMatch('/^[A-HJKMNP-Z2-9]{10}$/')
        ->and($first->code)->not->toBe($second->code)
        ->and($first->displayCode())->toMatch('/^.{4}-.{4}-.{2}$/');
});

it('knows which bookings are taking up time', function () {
    bookPriya('2026-11-02 09:00', '2026-11-02 09:30');
    bookPriya('2026-11-02 10:00', '2026-11-02 10:30', 'held');
    $stale = bookPriya('2026-11-02 11:00', '2026-11-02 11:30', 'held');
    $stale->update(['hold_expires_at' => now()->subMinute()]);
    bookPriya('2026-11-02 12:00', '2026-11-02 12:30', 'cancelled');

    expect(Booking::takingTime()->count())->toBe(2);
});
