<?php

use App\Enums\BookingStatus;
use App\Enums\BusinessRole;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use App\Models\TimeOff;
use App\Models\User;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Tuesday 27 October 2026, 08:00 in London (GMT, so UTC and local agree).
 * Online, the salon needs two hours' notice and takes bookings 30 days out;
 * a haircut is 45 minutes plus 15 to tidy up, with a £10 deposit online.
 */

beforeEach(function () {
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-27 08:00', 'UTC'));

    $this->owner = User::factory()->create();
    $this->business = Business::factory()->ownedBy($this->owner)->create([
        'name' => 'Glow Studio',
        'timezone' => 'Europe/London',
        'currency' => 'GBP',
        'min_notice_minutes' => 120,
        'booking_horizon_days' => 30,
    ]);

    $this->priyaUser = User::factory()->create();
    $this->business->addMember($this->priyaUser, BusinessRole::Staff);
    $this->priya = Staff::factory()->for($this->business)->create(['name' => 'Priya', 'user_id' => $this->priyaUser->id, 'position' => 1]);
    $this->sam = Staff::factory()->for($this->business)->create(['name' => 'Sam', 'position' => 2]);

    $this->haircut = Service::factory()->for($this->business)->withFixedDeposit(1000)->create([
        'name' => 'Haircut',
        'duration_minutes' => 45,
        'buffer_after_minutes' => 15,
        'price' => 3000,
    ]);
    $this->haircut->staff()->attach([$this->priya->id, $this->sam->id]);

    foreach ([$this->priya, $this->sam] as $staff) {
        $hour = new WorkingHour(['staff_id' => $staff->id, 'weekday' => 2, 'starts_at' => '09:00', 'ends_at' => '17:00']);
        $hour->business_id = $this->business->id;
        $hour->save();
    }
});

function newBooking(array $override = []): array
{
    return [
        'service' => test()->haircut->id,
        'staff' => test()->priya->id,
        'date' => '2026-10-27',
        'time' => '09:00',
        'name' => 'Ananya Iyer',
        'email' => 'Ananya@Example.com',
        'phone' => '+44 7700 900123',
        'notes' => 'Prefers the window seat.',
        'tell_customer' => true,
        ...$override,
    ];
}

it('adds a confirmed booking with nothing to pay, and emails the customer', function () {
    $this->actingAs($this->owner)
        ->post(route('bookings.store'), newBooking())
        ->assertRedirect(route('calendar', ['date' => '2026-10-27']))
        ->assertInertiaFlash('toast.message', 'Booked: Haircut with Priya, Tue 27 Oct at 9:00 am.');

    $booking = Booking::sole();
    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->source)->toBe('manual')
        ->and($booking->deposit)->toBe(0)
        ->and($booking->price)->toBe(3000)
        ->and($booking->currency)->toBe('GBP')
        ->and($booking->staff_id)->toBe($this->priya->id)
        ->and($booking->starts_at->toIso8601ZuluString())->toBe('2026-10-27T09:00:00Z')
        ->and($booking->ends_at->toIso8601ZuluString())->toBe('2026-10-27T09:45:00Z')
        ->and($booking->blocked_until->toIso8601ZuluString())->toBe('2026-10-27T10:00:00Z')
        ->and($booking->notes)->toBe('Prefers the window seat.')
        ->and($booking->customer->email)->toBe('ananya@example.com')
        ->and($booking->customer->phone)->toBe('+44 7700 900123');

    Mail::assertQueued(BookingConfirmedMail::class, fn ($mail) => $mail->hasTo('ananya@example.com'));
});

it('books far ahead, and outside working hours', function () {
    $this->actingAs($this->owner)
        ->post(route('bookings.store'), newBooking(['date' => '2027-06-01', 'time' => '19:30']))
        ->assertSessionHasNoErrors();

    expect(Booking::sole()->starts_at->toIso8601ZuluString())->toBe('2027-06-01T18:30:00Z');
});

it('takes the local time across a clock change', function () {
    // 29 March 2027: London is on summer time (BST, UTC+1).
    $this->actingAs($this->owner)->post(route('bookings.store'), newBooking(['date' => '2027-03-29', 'time' => '10:00']));

    expect(Booking::sole()->starts_at->toIso8601ZuluString())->toBe('2027-03-29T09:00:00Z');
});

it("won't overlap another booking or its tidy-up time, and keeps nothing from the attempt", function () {
    Booking::factory()->for($this->business)->at('2026-10-27 10:00', '2026-10-27 10:45', '2026-10-27 11:00')->create(['staff_id' => $this->priya->id]);

    $this->actingAs($this->owner)
        ->post(route('bookings.store'), newBooking(['time' => '10:50']))
        ->assertSessionHasErrors(['time' => 'Priya already has a booking then, or tidy-up time after one.']);

    expect(Booking::count())->toBe(1)
        ->and(Customer::where('email', 'ananya@example.com')->exists())->toBeFalse();
    Mail::assertNothingQueued();
});

it('takes the time of a hold that has run out', function () {
    $lapsed = Booking::factory()->for($this->business)->held()->at('2026-10-27 09:00', '2026-10-27 09:45')->create([
        'staff_id' => $this->priya->id,
        'hold_expires_at' => now()->subMinute(),
    ]);

    $this->actingAs($this->owner)->post(route('bookings.store'), newBooking())->assertSessionHasNoErrors();

    expect($lapsed->fresh()->status)->toBe(BookingStatus::Expired)
        ->and(Booking::where('source', 'manual')->sole()->status)->toBe(BookingStatus::Confirmed);
});

it("won't book over a hold that's still running", function () {
    Booking::factory()->for($this->business)->held(10)->at('2026-10-27 09:00', '2026-10-27 09:45')->create(['staff_id' => $this->priya->id]);

    $this->actingAs($this->owner)
        ->post(route('bookings.store'), newBooking())
        ->assertSessionHasErrors('time');
});

it("won't book over time off or a closure", function (?string $who, string $message) {
    TimeOff::factory()->for($this->business)->create([
        'staff_id' => $who === 'priya' ? $this->priya->id : null,
        'starts_at' => '2026-10-27 13:00',
        'ends_at' => '2026-10-27 14:00',
    ]);

    $this->actingAs($this->owner)
        ->post(route('bookings.store'), newBooking(['time' => '12:30']))
        ->assertSessionHasErrors(['time' => $message]);

    expect(Booking::count())->toBe(0);
})->with([
    'time off' => ['priya', 'Priya is off then.'],
    'closed' => [null, 'Glow Studio is closed then.'],
]);

it('ignores time off for someone else', function () {
    TimeOff::factory()->for($this->business)->create(['staff_id' => $this->sam->id, 'starts_at' => '2026-10-27 09:00', 'ends_at' => '2026-10-27 17:00']);

    $this->actingAs($this->owner)->post(route('bookings.store'), newBooking())->assertSessionHasNoErrors();
});

it('takes earlier today (a walk-in written up after), but not an earlier day', function () {
    $this->actingAs($this->owner)
        ->post(route('bookings.store'), newBooking(['date' => '2026-10-26', 'time' => '15:00']))
        ->assertSessionHasErrors(['date' => 'Pick today or a day after.']);

    $this->post(route('bookings.store'), newBooking(['time' => '07:00']))->assertSessionHasNoErrors();
});

it("won't book someone for a service they don't do", function () {
    $alex = Staff::factory()->for($this->business)->create(['name' => 'Alex']);

    $this->actingAs($this->owner)
        ->post(route('bookings.store'), newBooking(['staff' => $alex->id]))
        ->assertSessionHasErrors(['staff' => "Alex doesn't do Haircut."]);
});

it("won't book a service or person from another business", function (string $field) {
    $other = Business::factory()->create();
    $value = $field === 'service' ? Service::factory()->for($other)->create()->id : Staff::factory()->for($other)->create()->id;

    $this->actingAs($this->owner)
        ->post(route('bookings.store'), newBooking([$field => $value]))
        ->assertSessionHasErrors($field);

    expect(Booking::withoutGlobalScopes()->count())->toBe(0);
})->with(['service', 'staff']);

it('uses the customer already on file for an email, filling in only what was missing', function () {
    $ananya = Customer::factory()->for($this->business)->create(['name' => 'Ananya Iyer', 'email' => 'ananya@example.com', 'phone' => null]);

    $this->actingAs($this->owner)->post(route('bookings.store'), newBooking(['name' => 'A Iyer']));

    expect(Customer::count())->toBe(1)
        ->and(Booking::sole()->customer_id)->toBe($ananya->id)
        ->and($ananya->fresh()->name)->toBe('Ananya Iyer')
        ->and($ananya->fresh()->phone)->toBe('+44 7700 900123');
});

it('books someone picked from the customers on file', function () {
    $ananya = Customer::factory()->for($this->business)->create(['email' => 'ananya@example.com']);

    $this->actingAs($this->owner)
        ->post(route('bookings.store'), newBooking(['customer_id' => $ananya->id, 'name' => '', 'email' => '', 'phone' => '']))
        ->assertSessionHasNoErrors();

    expect(Booking::sole()->customer_id)->toBe($ananya->id);
    Mail::assertQueued(BookingConfirmedMail::class, fn ($mail) => $mail->hasTo('ananya@example.com'));
});

it("won't use a customer from another business", function () {
    $theirs = Customer::factory()->for(Business::factory())->create();

    $this->actingAs($this->owner)
        ->post(route('bookings.store'), newBooking(['customer_id' => $theirs->id]))
        ->assertSessionHasErrors('customer_id');

    expect(Booking::count())->toBe(0);
});

it('books a walk-in with no details at all, and emails nobody', function () {
    $this->actingAs($this->owner)
        ->post(route('bookings.store'), newBooking(['name' => '', 'email' => '', 'phone' => '']))
        ->assertSessionHasNoErrors();

    expect(Booking::sole()->customer_id)->toBeNull()
        ->and(Customer::count())->toBe(0);
    Mail::assertNothingQueued();
});

it('keeps a phone-only customer without an email', function () {
    $this->actingAs($this->owner)->post(route('bookings.store'), newBooking(['email' => '']));

    expect(Booking::sole()->customer->name)->toBe('Ananya Iyer')
        ->and(Booking::sole()->customer->email)->toBeNull();
    Mail::assertNothingQueued();
});

it('needs a name to go with contact details', function () {
    $this->actingAs($this->owner)
        ->post(route('bookings.store'), newBooking(['name' => '']))
        ->assertSessionHasErrors(['name' => 'Add their name to go with the contact details.']);
});

it("doesn't email when asked not to", function () {
    $this->actingAs($this->owner)->post(route('bookings.store'), newBooking(['tell_customer' => false]));

    expect(Booking::count())->toBe(1);
    Mail::assertNothingQueued();
});

it('lets staff book themselves in, and only themselves', function () {
    $this->actingAs($this->priyaUser)->post(route('bookings.store'), newBooking())->assertSessionHasNoErrors();
    $this->actingAs($this->priyaUser)->post(route('bookings.store'), newBooking(['staff' => $this->sam->id, 'time' => '11:00']))->assertForbidden();

    expect(Booking::pluck('staff_id')->all())->toBe([$this->priya->id]);
});

it('suggests free times without the online notice and horizon', function () {
    Booking::factory()->for($this->business)->at('2026-10-27 10:00', '2026-10-27 10:45', '2026-10-27 11:00')->create(['staff_id' => $this->priya->id]);

    $times = $this->actingAs($this->owner)
        ->getJson(route('bookings.times', ['service' => $this->haircut->id, 'staff' => $this->priya->id, 'date' => '2026-10-27']))
        ->assertOk()
        ->assertJsonPath('hours', [[540, 1020]])
        ->json('times');

    // 09:00 is inside the two hours' notice, but the business can still book it.
    expect($times)->toContain('2026-10-27T09:00:00Z')
        ->not->toContain('2026-10-27T10:00:00Z')
        ->not->toContain('2026-10-27T10:30:00Z');

    $farAhead = $this->getJson(route('bookings.times', ['service' => $this->haircut->id, 'staff' => $this->priya->id, 'date' => '2027-01-05']))->json('times');
    expect($farAhead)->not->toBeEmpty();
});

it('finds customers on file by name, email or phone, in this business only', function () {
    Customer::factory()->for($this->business)->create(['name' => 'Ananya Iyer', 'email' => 'ananya@example.com', 'phone' => '+44 7700 900123']);
    Customer::factory()->for($this->business)->create(['name' => 'Ben Carter', 'email' => 'ben@example.com', 'phone' => null]);
    Customer::factory()->for(Business::factory())->create(['name' => 'Ananya Other', 'email' => 'other@example.com']);

    $search = fn (string $q) => collect($this->actingAs($this->owner)->getJson(route('customers.search', ['search' => $q]))->assertOk()->json())->pluck('name')->all();

    expect($search('anan'))->toBe(['Ananya Iyer'])
        ->and($search('BEN@'))->toBe(['Ben Carter'])
        ->and($search('7700 900'))->toBe(['Ananya Iyer'])
        ->and($search('a'))->toBe([])
        ->and($search('100%'))->toBe([]);
});

it('gives the calendar what the form needs, for owners and for staff', function () {
    Staff::factory()->for($this->business)->create(['name' => 'Gone', 'is_active' => false]);
    Service::factory()->for($this->business)->create(['name' => 'Retired', 'is_active' => false]);

    $this->actingAs($this->owner)
        ->get(route('calendar'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('newBooking.services', fn ($services) => collect($services)->pluck('name')->all() === ['Haircut'])
            ->where('newBooking.services.0.staff_ids', [$this->priya->id, $this->sam->id])
            ->where('newBooking.services.0.duration', 45)
            ->where('newBooking.staff', fn ($staff) => collect($staff)->pluck('name')->all() === ['Priya', 'Sam'])
            ->where('newBooking.currency', 'GBP'));

    $this->actingAs($this->priyaUser)
        ->get(route('calendar'))
        ->assertInertia(fn (Assert $page) => $page->where('newBooking.staff', fn ($staff) => collect($staff)->pluck('name')->all() === ['Priya']));
});
