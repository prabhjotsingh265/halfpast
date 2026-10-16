<?php

use App\Actions\Booking\FindBookableTimes;
use App\Availability\Slot;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Service;
use App\Models\Staff;
use App\Models\TimeOff;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;

/*
 * Monday 2 November 2026, London (GMT, so local time reads as UTC).
 * Priya works 09:00–12:00 on Mondays and does a one-hour haircut.
 */

beforeEach(function () {
    $this->now = CarbonImmutable::parse('2026-10-26 08:00', 'UTC');
    $this->business = Business::factory()->create([
        'timezone' => 'Europe/London',
        'slot_step_minutes' => 60,
        'min_notice_minutes' => 120,
        'booking_horizon_days' => 60,
    ]);
    $this->haircut = Service::factory()->for($this->business)->create(['duration_minutes' => 60, 'buffer_after_minutes' => 0]);
    $this->priya = mondayMorningsFor($this->business);
    $this->haircut->staff()->attach($this->priya);
});

function mondayMorningsFor(Business $business, array $attributes = []): Staff
{
    $staff = Staff::factory()->for($business)->create($attributes);
    $hour = new WorkingHour(['staff_id' => $staff->id, 'weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00']);
    $hour->business_id = $business->id;
    $hour->save();

    return $staff;
}

/** @return list<string> */
function bookableOn(string $date, ?Staff $only = null, ?CarbonImmutable $now = null): array
{
    $slots = app(FindBookableTimes::class)->handle(test()->business, test()->haircut, $only, $date, $date, $now ?? test()->now);

    return array_map(fn (Slot $slot) => $slot->start->setTimezone(new DateTimeZone('Europe/London'))->format('H:i'), $slots);
}

it('offers the free hours of the people who do the service', function () {
    expect(bookableOn('2026-11-02'))->toBe(['09:00', '10:00', '11:00']);
});

it('leaves out confirmed bookings and live holds, but not lapsed ones', function () {
    Booking::factory()->for($this->business)->at('2026-11-02 09:00', '2026-11-02 10:00')->create(['staff_id' => $this->priya->id]);
    Booking::factory()->for($this->business)->at('2026-11-02 10:00', '2026-11-02 11:00')
        ->create(['staff_id' => $this->priya->id, 'status' => BookingStatus::Held, 'hold_expires_at' => $this->now->addMinutes(5)]);
    Booking::factory()->for($this->business)->at('2026-11-02 11:00', '2026-11-02 12:00')
        ->create(['staff_id' => $this->priya->id, 'status' => BookingStatus::Held, 'hold_expires_at' => $this->now->subMinute()]);

    expect(bookableOn('2026-11-02'))->toBe(['11:00']);
});

it('keeps the free time after a booking clear', function () {
    Booking::factory()->for($this->business)->at('2026-11-02 09:00', '2026-11-02 09:30', '2026-11-02 10:15')
        ->create(['staff_id' => $this->priya->id]);

    expect(bookableOn('2026-11-02'))->toBe(['11:00']);
});

it('leaves out time off and closures', function () {
    TimeOff::factory()->for($this->business)->create([
        'staff_id' => $this->priya->id,
        'starts_at' => '2026-11-02 09:00',
        'ends_at' => '2026-11-02 10:00',
    ]);
    TimeOff::factory()->for($this->business)->create([
        'starts_at' => '2026-11-02 11:00',
        'ends_at' => '2026-11-02 12:00',
    ]);

    expect(bookableOn('2026-11-02'))->toBe(['10:00']);
});

it('only counts people who do the service and are taking bookings', function () {
    $sam = mondayMorningsFor($this->business, ['is_active' => false]);
    $this->haircut->staff()->attach($sam);
    $alex = mondayMorningsFor($this->business);

    Booking::factory()->for($this->business)->at('2026-11-02 09:00', '2026-11-02 12:00')->create(['staff_id' => $this->priya->id]);

    expect(bookableOn('2026-11-02'))->toBe([])
        ->and(app(FindBookableTimes::class)->staffFor($this->business, $this->haircut)->pluck('id')->all())->toBe([$this->priya->id])
        ->and($alex->services)->toHaveCount(0);
});

it('can look at one person, or everyone who does the service', function () {
    $sam = mondayMorningsFor($this->business);
    $this->haircut->staff()->attach($sam);
    Booking::factory()->for($this->business)->at('2026-11-02 09:00', '2026-11-02 12:00')->create(['staff_id' => $this->priya->id]);

    expect(bookableOn('2026-11-02', $this->priya))->toBe([])
        ->and(bookableOn('2026-11-02', $sam))->toBe(['09:00', '10:00', '11:00'])
        ->and(bookableOn('2026-11-02'))->toBe(['09:00', '10:00', '11:00']);
});

it('keeps to the minimum notice and how far ahead bookings go', function () {
    // Two hours' notice at 08:30 on the day: 09:00 and 10:00 are too soon.
    expect(bookableOn('2026-11-02', now: CarbonImmutable::parse('2026-11-02 08:30', 'UTC')))->toBe(['11:00']);

    $this->business->update(['booking_horizon_days' => 3]);

    expect(bookableOn('2026-11-02'))->toBe([]);
});

it("ignores other businesses' bookings", function () {
    $other = Business::factory()->create();
    Booking::factory()->for($other)->at('2026-11-02 09:00', '2026-11-02 12:00')->create();

    expect(bookableOn('2026-11-02'))->toBe(['09:00', '10:00', '11:00']);
});
