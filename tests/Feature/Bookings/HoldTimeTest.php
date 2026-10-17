<?php

use App\Enums\BookingStatus;
use App\Http\Controllers\HoldController;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Service;
use App\Models\Staff;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Monday 26 October 2026, 08:00 in London. Priya and Sam both work 09:00
 * to 12:00 on weekdays and both do a 60 minute haircut with 15 minutes
 * to tidy up after.
 */

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00', 'UTC'));

    $this->business = Business::factory()->create([
        'slug' => 'glow-studio',
        'timezone' => 'Europe/London',
        'slot_step_minutes' => 60,
        'min_notice_minutes' => 60,
        'hold_minutes' => 10,
        'payment_gateways' => ['test'],
    ]);
    $this->haircut = Service::factory()->for($this->business)->withPercentDeposit(20)
        ->create(['name' => 'Haircut', 'duration_minutes' => 60, 'buffer_after_minutes' => 15, 'price' => 3000]);
    $this->priya = weekdayMorningsAt($this->business, 'Priya');
    $this->sam = weekdayMorningsAt($this->business, 'Sam');
    $this->haircut->staff()->attach([$this->priya->id, $this->sam->id]);
});

function weekdayMorningsAt(Business $business, string $name): Staff
{
    $staff = Staff::factory()->for($business)->create(['name' => $name, 'position' => Staff::count()]);

    foreach (range(1, 5) as $weekday) {
        $hour = new WorkingHour(['staff_id' => $staff->id, 'weekday' => $weekday, 'starts_at' => '09:00', 'ends_at' => '12:00']);
        $hour->business_id = $business->id;
        $hour->save();
    }

    return $staff;
}

function holdAt(string $startsAt, ?Staff $staff = null): TestResponse
{
    return test()->post('/glow-studio/hold', [
        'service_id' => test()->haircut->id,
        'staff_id' => $staff?->id,
        'starts_at' => $startsAt,
    ]);
}

it('holds a time for ten minutes and goes on to the checkout', function () {
    $response = holdAt('2026-10-27T10:00:00Z', $this->priya);

    $booking = Booking::sole();
    $response->assertRedirect("/glow-studio/checkout/{$booking->code}");

    expect($booking->status)->toBe(BookingStatus::Held)
        ->and($booking->staff_id)->toBe($this->priya->id)
        ->and($booking->service_name)->toBe('Haircut')
        ->and($booking->ends_at->toIso8601ZuluString())->toBe('2026-10-27T11:00:00Z')
        ->and($booking->blocked_until->toIso8601ZuluString())->toBe('2026-10-27T11:15:00Z')
        ->and($booking->hold_expires_at->toIso8601ZuluString())->toBe('2026-10-26T08:10:00Z')
        ->and($booking->price)->toBe(3000)
        ->and($booking->deposit)->toBe(600)
        ->and(session(HoldController::SESSION_KEY))->toBe([$booking->code]);

    $this->get("/glow-studio/checkout/{$booking->code}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('booking/Checkout')
            ->where('booking.held', true)
            ->where('booking.staff_name', 'Priya')
            ->where('booking.hold_expires_at', '2026-10-26T08:10:00Z')
            ->where('holdMinutes', 10));
});

it('gives "anyone free" to whoever has the fewest bookings that day', function () {
    Booking::factory()->for($this->business)->at('2026-10-27 09:00', '2026-10-27 10:00')->create(['staff_id' => $this->priya->id]);

    holdAt('2026-10-27T11:00:00Z');

    expect(Booking::where('status', BookingStatus::Held)->sole()->staff_id)->toBe($this->sam->id);
});

it('tells the customer when someone else got there first', function () {
    Booking::factory()->for($this->business)->at('2026-10-27 10:00', '2026-10-27 11:00')->create(['staff_id' => $this->priya->id]);

    $this->from('/glow-studio')
        ->post('/glow-studio/hold', ['service_id' => $this->haircut->id, 'staff_id' => $this->priya->id, 'starts_at' => '2026-10-27T10:00:00Z'])
        ->assertRedirect('/glow-studio')
        ->assertSessionHasErrors('starts_at');

    expect(Booking::count())->toBe(1);
});

it("won't hold a time the rules don't offer", function (string $startsAt) {
    holdAt($startsAt, $this->priya)->assertSessionHasErrors('starts_at');

    expect(Booking::count())->toBe(0);
})->with([
    'too soon' => ['2026-10-26T08:30:00Z'],
    'outside hours' => ['2026-10-27T14:00:00Z'],
    'off the slot grid' => ['2026-10-27T10:20:00Z'],
    'a weekend' => ['2026-10-31T10:00:00Z'],
]);

it('lets a browser hold one time at once', function () {
    holdAt('2026-10-27T09:00:00Z', $this->priya);
    holdAt('2026-10-27T10:00:00Z', $this->priya);

    expect(Booking::where('status', BookingStatus::Held)->sole()->starts_at->toIso8601ZuluString())->toBe('2026-10-27T10:00:00Z')
        ->and(Booking::where('status', BookingStatus::Expired)->count())->toBe(1);
});

it('goes back to the same hold when the time is held again', function () {
    holdAt('2026-10-27T09:00:00Z', $this->priya);
    $booking = Booking::sole();

    holdAt('2026-10-27T09:00:00Z')->assertRedirect("/glow-studio/checkout/{$booking->code}");

    expect(Booking::count())->toBe(1);
});

it('keeps the first hold when a second time turns out to be taken', function () {
    holdAt('2026-10-27T09:00:00Z', $this->priya);
    $first = Booking::sole();
    Booking::factory()->for($this->business)->at('2026-10-27 11:00', '2026-10-27 12:00')->create(['staff_id' => $this->priya->id]);

    holdAt('2026-10-27T11:00:00Z', $this->priya)->assertSessionHasErrors('starts_at');

    expect($first->fresh()->status)->toBe(BookingStatus::Held)
        ->and(session(HoldController::SESSION_KEY))->toBe([$first->code]);
});

it('frees a lapsed hold for the next customer straight away', function () {
    holdAt('2026-10-27T09:00:00Z', $this->priya);
    $first = Booking::sole();

    $this->travel(11)->minutes();
    // Someone else, in another browser.
    $this->flushSession();

    holdAt('2026-10-27T09:00:00Z', $this->priya)->assertSessionHasNoErrors();

    expect($first->fresh()->status)->toBe(BookingStatus::Expired)
        ->and(Booking::where('status', BookingStatus::Held)->count())->toBe(1);
});

it('lets the customer give the time back and choose again', function () {
    holdAt('2026-10-27T09:00:00Z', $this->priya);
    $booking = Booking::sole();

    $this->delete("/glow-studio/hold/{$booking->code}")
        ->assertRedirect('/glow-studio?service='.$this->haircut->id.'&date=2026-10-27');

    expect($booking->fresh()->status)->toBe(BookingStatus::Expired);
});

it('shows the checkout only to the browser that made the hold', function () {
    holdAt('2026-10-27T09:00:00Z', $this->priya);
    $booking = Booking::sole();

    $this->flushSession();

    $this->get("/glow-studio/checkout/{$booking->code}")->assertNotFound();
    $this->delete("/glow-studio/hold/{$booking->code}")->assertNotFound();
});

it('shows a hold that has run out', function () {
    holdAt('2026-10-27T09:00:00Z', $this->priya);
    $booking = Booking::sole();

    $this->travel(10)->minutes();

    $this->get("/glow-studio/checkout/{$booking->code}")
        ->assertInertia(fn (Assert $page) => $page->where('booking.held', false));
});

it("won't hold another business's service or staff", function () {
    $other = Business::factory()->create();
    $theirService = Service::factory()->for($other)->create();
    $theirStaff = Staff::factory()->for($other)->create();

    $this->post('/glow-studio/hold', ['service_id' => $theirService->id, 'staff_id' => $theirStaff->id, 'starts_at' => '2026-10-27T09:00:00Z'])
        ->assertSessionHasErrors(['service_id', 'staff_id']);
});
