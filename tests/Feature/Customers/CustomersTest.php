<?php

use App\Enums\BookingStatus;
use App\Enums\BusinessRole;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Staff;
use App\Models\User;
use App\Tenancy\CurrentBusiness;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Tuesday 27 October 2026, 10:00 UTC, in a London salon.
 */

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-27 10:00', 'UTC'));
    $this->owner = User::factory()->create();
    $this->business = Business::factory()->ownedBy($this->owner)->create(['timezone' => 'Europe/London', 'currency' => 'GBP']);
    app(CurrentBusiness::class)->set($this->business);
    $this->staff = Staff::factory()->for($this->business)->create(['name' => 'Priya']);
});

function customerVisit(Customer $customer, string $start, BookingStatus $status = BookingStatus::Completed, array $override = []): Booking
{
    $end = CarbonImmutable::parse("{$start} UTC")->addHour()->format('Y-m-d H:i');

    return Booking::factory()->for(test()->business)->at("{$start} UTC", "{$end} UTC")->create([
        'staff_id' => test()->staff->id,
        'customer_id' => $customer->id,
        'status' => $status,
        ...$override,
    ]);
}

function customersPage(array $query = []): array
{
    return test()->actingAs(test()->owner)->get(route('customers.index', $query))->assertOk()->viewData('page')['props'];
}

it('lists customers with their visits, no-shows, and when they were last in or are next due', function () {
    $ananya = Customer::factory()->for($this->business)->create(['name' => 'Ananya Iyer', 'email' => 'ananya@example.com', 'phone' => '07700 900123']);
    customerVisit($ananya, '2026-10-01 10:00');
    customerVisit($ananya, '2026-10-10 10:00');
    customerVisit($ananya, '2026-10-20 10:00', BookingStatus::NoShow);
    customerVisit($ananya, '2026-11-03 10:00', BookingStatus::Confirmed);
    customerVisit($ananya, '2026-10-25 10:00', BookingStatus::Cancelled);
    Customer::factory()->for($this->business)->create(['name' => 'Gone', 'forgotten_at' => now()]);

    $props = customersPage();

    expect($props['total'])->toBe(1)
        ->and($props['customers']['data'])->toHaveCount(1)
        ->and($props['customers']['data'][0])->toMatchArray([
            'name' => 'Ananya Iyer',
            'visits' => 2,
            'no_shows' => 1,
            'last_seen_at' => '2026-10-20T10:00:00Z',
            'next_at' => '2026-11-03T10:00:00Z',
        ]);
});

it('finds customers by name, email or phone digits', function (string $search, array $expected) {
    Customer::factory()->for($this->business)->create(['name' => 'Ananya Iyer', 'email' => 'ananya@example.com', 'phone' => '+44 7700 900123']);
    Customer::factory()->for($this->business)->create(['name' => 'Ben Carter', 'email' => 'ben@example.com', 'phone' => null]);
    Customer::factory()->for(Business::factory())->create(['name' => 'Ananya Elsewhere']);

    $names = collect(customersPage(['search' => $search])['customers']['data'])->pluck('name')->sort()->values()->all();

    expect($names)->toBe($expected);
})->with([
    'name' => ['anan', ['Ananya Iyer']],
    'email' => ['BEN@EX', ['Ben Carter']],
    'phone, written differently' => ['7700-900', ['Ananya Iyer']],
    'too short to search' => ['a', ['Ananya Iyer', 'Ben Carter']],
    'nobody' => ['zzz', []],
]);

it('sorts by when they were last in, by name, or by visits', function () {
    $zoe = Customer::factory()->for($this->business)->create(['name' => 'Zoe', 'created_at' => now()->subDays(30)]);
    $amir = Customer::factory()->for($this->business)->create(['name' => 'Amir', 'created_at' => now()->subDays(20)]);
    $new = Customer::factory()->for($this->business)->create(['name' => 'Mia', 'created_at' => now()->subDay()]);
    customerVisit($zoe, '2026-10-25 10:00');
    customerVisit($amir, '2026-10-01 10:00');
    customerVisit($amir, '2026-10-05 10:00');

    $order = fn (array $query) => collect(customersPage($query)['customers']['data'])->pluck('name')->all();

    // Last seen first; someone who's never been in goes after, by when they were added.
    expect($order([]))->toBe(['Zoe', 'Amir', 'Mia'])
        ->and($order(['sort' => 'name']))->toBe(['Amir', 'Mia', 'Zoe'])
        ->and($order(['sort' => 'visits']))->toBe(['Amir', 'Zoe', 'Mia'])
        ->and($order(['sort' => 'nonsense']))->toBe(['Zoe', 'Amir', 'Mia']);

    expect($new->exists)->toBeTrue();
});

it('shows 25 at a time', function () {
    Customer::factory()->for($this->business)->count(30)->create();

    $first = customersPage();
    $second = customersPage(['page' => 2]);

    expect($first['customers']['data'])->toHaveCount(25)
        ->and($first['customers']['last_page'])->toBe(2)
        ->and($second['customers']['data'])->toHaveCount(5);
});

it("shows one customer's history, newest first, with what they've paid", function () {
    $ananya = Customer::factory()->for($this->business)->create(['name' => 'Ananya Iyer', 'notes' => 'Allergic to latex', 'created_at' => '2026-09-01 12:00']);
    $done = customerVisit($ananya, '2026-10-01 10:00', BookingStatus::Completed, ['deposit' => 1000]);
    Payment::factory()->create(['booking_id' => $done->id, 'business_id' => $this->business->id, 'amount' => 1000, 'currency' => 'GBP', 'status' => PaymentStatus::Captured]);
    $cancelled = customerVisit($ananya, '2026-10-15 10:00', BookingStatus::Cancelled, ['deposit' => 1000]);
    Payment::factory()->create(['booking_id' => $cancelled->id, 'business_id' => $this->business->id, 'amount' => 1000, 'refunded_amount' => 1000, 'currency' => 'GBP', 'status' => PaymentStatus::Refunded]);
    customerVisit($ananya, '2026-10-20 10:00', BookingStatus::NoShow);
    $next = customerVisit($ananya, '2026-11-03 10:00', BookingStatus::Confirmed);
    customerVisit($ananya, '2026-10-27 15:00', BookingStatus::Expired);

    $this->actingAs($this->owner)
        ->get(route('customers.show', $ananya))
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/Show')
            ->where('customer.name', 'Ananya Iyer')
            ->where('customer.notes', 'Allergic to latex')
            ->where('customer.since', '2026-09-01T12:00:00Z')
            ->where('customer.forgotten', false)
            ->where('stats', ['visits' => 1, 'upcoming' => 1, 'noShows' => 1, 'cancelled' => 1, 'depositsKept' => 1000, 'currency' => 'GBP'])
            ->has('bookings', 4)
            ->where('bookings.0.code', $next->code)
            ->where('bookings.0.staff_name', 'Priya')
            ->where('bookings.0.can.cancel', true)
            ->where('bookings.3.code', $done->code)
            ->has('newBooking.staff', 1));
});

it('saves contact details and notes', function () {
    $ananya = Customer::factory()->for($this->business)->create(['name' => 'Ananya', 'email' => 'old@example.com', 'phone' => '0123 456789']);

    $this->actingAs($this->owner)
        ->from(route('customers.show', $ananya))
        ->put(route('customers.update', $ananya), ['name' => ' Ananya Iyer ', 'email' => 'Ananya@Example.com', 'phone' => '', 'notes' => 'Prefers mornings'])
        ->assertRedirect(route('customers.show', $ananya))
        ->assertInertiaFlash('toast.message', 'Saved.');

    expect($ananya->fresh())
        ->name->toBe('Ananya Iyer')
        ->email->toBe('ananya@example.com')
        ->phone->toBeNull()
        ->notes->toBe('Prefers mornings');
});

it("won't give two customers the same email, within a business only", function () {
    Customer::factory()->for($this->business)->create(['email' => 'taken@example.com']);
    Customer::factory()->for(Business::factory())->create(['email' => 'elsewhere@example.com']);
    $ananya = Customer::factory()->for($this->business)->create(['email' => 'ananya@example.com']);

    $this->actingAs($this->owner)
        ->put(route('customers.update', $ananya), ['name' => 'Ananya', 'email' => 'taken@example.com'])
        ->assertSessionHasErrors(['email' => 'Another customer already has that email address.']);

    $this->put(route('customers.update', $ananya), ['name' => 'Ananya', 'email' => 'elsewhere@example.com'])->assertSessionHasNoErrors();
    $this->put(route('customers.update', $ananya), ['name' => 'Ananya', 'email' => 'elsewhere@example.com'])->assertSessionHasNoErrors();
});

it('removes a customer\'s details on request, keeping their past bookings', function () {
    $ananya = Customer::factory()->for($this->business)->create(['name' => 'Ananya Iyer', 'email' => 'ananya@example.com', 'phone' => '07700 900123', 'notes' => 'Allergic to latex']);
    $past = customerVisit($ananya, '2026-10-01 10:00', BookingStatus::Completed, ['notes' => 'Window seat please']);
    customerVisit($ananya, '2026-10-20 10:00', BookingStatus::Cancelled);

    $this->actingAs($this->owner)
        ->delete(route('customers.destroy', $ananya))
        ->assertRedirect(route('customers.index'));

    $ananya->refresh();
    expect($ananya->name)->toBe('Removed customer')
        ->and($ananya->email)->toBeNull()
        ->and($ananya->phone)->toBeNull()
        ->and($ananya->notes)->toBeNull()
        ->and($ananya->forgotten_at)->not->toBeNull()
        ->and($past->fresh()->notes)->toBeNull()
        ->and($past->fresh()->customer_id)->toBe($ananya->id)
        ->and(customersPage()['total'])->toBe(0);

    // Not offered in the new-booking search any more either.
    $this->getJson(route('customers.search', ['search' => 'Removed']))->assertExactJson([]);

    // Their page still opens, saying what happened, but can't be edited.
    $this->get(route('customers.show', $ananya))->assertInertia(fn (Assert $page) => $page->where('customer.forgotten', true));
    $this->put(route('customers.update', $ananya), ['name' => 'Back again'])->assertNotFound();
    $this->delete(route('customers.destroy', $ananya))->assertNotFound();
});

it("won't remove someone with a booking still to come", function () {
    $ananya = Customer::factory()->for($this->business)->create(['name' => 'Ananya Iyer']);
    customerVisit($ananya, '2026-11-03 10:00', BookingStatus::Confirmed);

    $this->actingAs($this->owner)
        ->delete(route('customers.destroy', $ananya))
        ->assertInertiaFlash('toast.type', 'error');

    expect($ananya->fresh()->name)->toBe('Ananya Iyer')
        ->and($ananya->fresh()->forgotten_at)->toBeNull();
});

it('is for owners only', function () {
    $staffUser = User::factory()->create();
    $this->business->addMember($staffUser, BusinessRole::Staff);
    $ananya = Customer::factory()->for($this->business)->create();

    $this->actingAs($staffUser)->get(route('customers.index'))->assertForbidden();
    $this->actingAs($staffUser)->get(route('customers.show', $ananya))->assertForbidden();
    $this->actingAs($staffUser)->put(route('customers.update', $ananya), ['name' => 'X'])->assertForbidden();
    $this->actingAs($staffUser)->delete(route('customers.destroy', $ananya))->assertForbidden();
});

it("keeps other businesses' customers out of reach", function () {
    $theirs = Customer::factory()->for(Business::factory())->create(['name' => 'Theirs']);

    $this->actingAs($this->owner)->get(route('customers.show', $theirs->id))->assertNotFound();
    $this->actingAs($this->owner)->put(route('customers.update', $theirs->id), ['name' => 'Mine now'])->assertNotFound();
    $this->actingAs($this->owner)->delete(route('customers.destroy', $theirs->id))->assertNotFound();

    expect($theirs->fresh()->name)->toBe('Theirs');
});
