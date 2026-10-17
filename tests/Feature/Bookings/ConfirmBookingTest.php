<?php

use App\Actions\Booking\ConfirmBooking;
use App\Enums\BookingStatus;
use App\Events\BookingConfirmed;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Monday 26 October 2026, 08:00 in London. Priya works weekday mornings;
 * a trim has nothing to pay up front, a colour takes a £20 deposit.
 */

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00', 'UTC'));

    $this->business = Business::factory()->create(['slug' => 'glow-studio', 'timezone' => 'Europe/London', 'slot_step_minutes' => 60, 'min_notice_minutes' => 60]);
    $this->trim = Service::factory()->for($this->business)->create(['name' => 'Trim', 'duration_minutes' => 60, 'price' => 2500]);
    $this->colour = Service::factory()->for($this->business)->withFixedDeposit(2000)->create(['name' => 'Colour', 'duration_minutes' => 60, 'price' => 8000]);
    $this->priya = Staff::factory()->for($this->business)->create(['name' => 'Priya']);
    $this->trim->staff()->attach($this->priya);
    $this->colour->staff()->attach($this->priya);

    foreach (range(1, 5) as $weekday) {
        $hour = new WorkingHour(['staff_id' => $this->priya->id, 'weekday' => $weekday, 'starts_at' => '09:00', 'ends_at' => '12:00']);
        $hour->business_id = $this->business->id;
        $hour->save();
    }
});

function holdService(Service $service, string $startsAt = '2026-10-27T09:00:00Z'): Booking
{
    test()->post('/glow-studio/hold', ['service_id' => $service->id, 'starts_at' => $startsAt]);

    return Booking::latest('id')->firstOrFail();
}

function details(array $override = []): array
{
    return ['name' => 'Ananya Iyer', 'email' => 'Ananya@Example.com', 'phone' => '+44 7700 900123', 'notes' => 'Shorter at the back.', ...$override];
}

it('confirms a booking with nothing to pay as soon as the details are in', function () {
    Event::fake([BookingConfirmed::class]);
    $booking = holdService($this->trim);

    $response = $this->post("/glow-studio/checkout/{$booking->code}", details());

    $booking->refresh();
    $response->assertRedirect($booking->manageUrl());

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->hold_expires_at)->toBeNull()
        ->and($booking->notes)->toBe('Shorter at the back.')
        ->and($booking->customer->name)->toBe('Ananya Iyer')
        ->and($booking->customer->email)->toBe('ananya@example.com')
        ->and($booking->customer->business_id)->toBe($this->business->id);

    Event::assertDispatchedTimes(BookingConfirmed::class, 1);
});

it('keeps a booking with a deposit held, waiting for payment', function () {
    Event::fake([BookingConfirmed::class]);
    $booking = holdService($this->colour);

    $this->post("/glow-studio/checkout/{$booking->code}", details())
        ->assertRedirect("/glow-studio/checkout/{$booking->code}");

    expect($booking->fresh()->status)->toBe(BookingStatus::Held)
        ->and($booking->fresh()->customer_id)->not->toBeNull();

    $this->get("/glow-studio/checkout/{$booking->code}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('customer.name', 'Ananya Iyer')
            ->where('booking.deposit', 2000));

    Event::assertNotDispatched(BookingConfirmed::class);
});

it('keeps one customer record per email address', function () {
    Customer::factory()->for($this->business)->create(['email' => 'ananya@example.com', 'name' => 'A Iyer', 'phone' => null]);

    $booking = holdService($this->trim);
    $this->post("/glow-studio/checkout/{$booking->code}", details());

    expect(Customer::count())->toBe(1)
        ->and(Customer::sole()->name)->toBe('Ananya Iyer')
        ->and(Customer::sole()->phone)->toBe('+44 7700 900123');
});

it('keeps customers of different businesses apart', function () {
    $other = Business::factory()->create();
    Customer::factory()->for($other)->create(['email' => 'ananya@example.com']);

    $booking = holdService($this->trim);
    $this->post("/glow-studio/checkout/{$booking->code}", details());

    expect($booking->fresh()->customer->business_id)->toBe($this->business->id)
        ->and(Customer::withoutGlobalScopes()->count())->toBe(2);
});

it('confirms exactly once, however many times it is asked', function () {
    Event::fake([BookingConfirmed::class]);
    $booking = holdService($this->trim);

    $first = app(ConfirmBooking::class)->handle($booking);
    $second = app(ConfirmBooking::class)->handle($booking);

    expect($first)->toBeTrue()->and($second)->toBeFalse();
    Event::assertDispatchedTimes(BookingConfirmed::class, 1);
});

it('still books a lapsed hold if nobody has taken the time', function () {
    $booking = holdService($this->trim);
    $this->travel(15)->minutes();

    $this->get("/glow-studio/checkout/{$booking->code}")
        ->assertInertia(fn (Assert $page) => $page->where('booking.held', false)->where('booking.renewable', true));

    $this->post("/glow-studio/checkout/{$booking->code}", details());

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it('holds the time again if a cleared hold is still free', function () {
    $booking = holdService($this->trim);
    $this->travel(15)->minutes();
    $this->artisan('bookings:expire-holds');

    $this->get("/glow-studio/checkout/{$booking->code}")
        ->assertInertia(fn (Assert $page) => $page->where('booking.renewable', true));

    $response = $this->post("/glow-studio/checkout/{$booking->code}", details());

    $fresh = Booking::where('status', BookingStatus::Confirmed)->sole();
    $response->assertRedirect($fresh->manageUrl());

    expect($fresh->id)->not->toBe($booking->id)
        ->and($fresh->starts_at->equalTo($booking->starts_at))->toBeTrue()
        ->and($fresh->staff_id)->toBe($booking->staff_id)
        ->and($booking->fresh()->status)->toBe(BookingStatus::Expired);
});

it('says so when a lapsed hold went to someone else', function () {
    $booking = holdService($this->trim);
    $this->travel(15)->minutes();
    $this->artisan('bookings:expire-holds');
    Booking::factory()->for($this->business)->at('2026-10-27 09:00', '2026-10-27 10:00')->create(['staff_id' => $this->priya->id]);

    $this->get("/glow-studio/checkout/{$booking->code}")
        ->assertInertia(fn (Assert $page) => $page->where('booking.renewable', false));

    $this->from("/glow-studio/checkout/{$booking->code}")
        ->post("/glow-studio/checkout/{$booking->code}", details())
        ->assertSessionHasErrors('booking');

    expect($booking->fresh()->status)->toBe(BookingStatus::Expired);
});

it('checks the details', function (array $override, string $field) {
    $booking = holdService($this->trim);

    $this->post("/glow-studio/checkout/{$booking->code}", details($override))->assertSessionHasErrors($field);

    expect($booking->fresh()->status)->toBe(BookingStatus::Held);
})->with([
    'no name' => [['name' => ''], 'name'],
    'not an email' => [['email' => 'ananya'], 'email'],
    'odd phone' => [['phone' => 'call me'], 'phone'],
]);

it('only takes details from the browser that made the hold', function () {
    $booking = holdService($this->trim);
    $this->flushSession();

    $this->post("/glow-studio/checkout/{$booking->code}", details())->assertNotFound();
});

it("shows the customer's booking on its signed link", function () {
    $booking = holdService($this->trim);
    $this->post("/glow-studio/checkout/{$booking->code}", details());
    $booking->refresh();

    $this->flushSession();

    $this->get($booking->manageUrl())
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('booking/Manage')
            ->where('booking.status', 'confirmed')
            ->where('booking.service_name', 'Trim')
            ->where('booking.staff_name', 'Priya')
            ->where('booking.customer_email', 'ananya@example.com')
            ->where('booking.code', $booking->displayCode()));

    // Unsigned, or with the signature changed: refused.
    $this->get("/glow-studio/booking/{$booking->code}")->assertForbidden();
    $this->get($booking->manageUrl().'0')->assertForbidden();
});

it('sends a confirmed booking from the checkout to its own page', function () {
    $booking = holdService($this->trim);
    $this->post("/glow-studio/checkout/{$booking->code}", details());

    $this->get("/glow-studio/checkout/{$booking->code}")->assertRedirect($booking->fresh()->manageUrl());
});
