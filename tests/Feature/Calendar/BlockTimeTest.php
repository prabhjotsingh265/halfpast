<?php

use App\Enums\BookingStatus;
use App\Enums\BusinessRole;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Service;
use App\Models\Staff;
use App\Models\TimeOff;
use App\Models\User;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Tuesday 6 April 2027, 08:00 in London: summer time (UTC+1), so a local
 * 13:00 is 12:00 UTC.
 */

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2027-04-06 07:00', 'UTC'));

    $this->owner = User::factory()->create();
    $this->business = Business::factory()->ownedBy($this->owner)->create(['name' => 'Glow Studio', 'timezone' => 'Europe/London']);

    $this->priyaUser = User::factory()->create();
    $this->business->addMember($this->priyaUser, BusinessRole::Staff);
    $this->priya = Staff::factory()->for($this->business)->create(['name' => 'Priya', 'user_id' => $this->priyaUser->id, 'position' => 1]);
    $this->sam = Staff::factory()->for($this->business)->create(['name' => 'Sam', 'position' => 2]);
});

function block(array $override = []): array
{
    return [
        'staff_id' => test()->priya->id,
        'all_day' => false,
        'starts_on' => '2027-04-06',
        'ends_on' => '2027-04-06',
        'starts_time' => '13:00',
        'ends_time' => '14:00',
        'reason' => 'Lunch',
        ...$override,
    ];
}

it('blocks out part of a day for someone, stored as the real moments', function () {
    $this->actingAs($this->owner)
        ->post(route('calendar.blocks.store'), block())
        ->assertRedirect(route('calendar', ['date' => '2027-04-06']))
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'Priya is blocked out from 1:00 pm to 2:00 pm.');

    $off = TimeOff::sole();
    expect($off->staff_id)->toBe($this->priya->id)
        ->and($off->reason)->toBe('Lunch')
        ->and($off->starts_at->toIso8601ZuluString())->toBe('2027-04-06T12:00:00Z')
        ->and($off->ends_at->toIso8601ZuluString())->toBe('2027-04-06T13:00:00Z');
});

it('says when bookings are still in the time, and leaves them be', function () {
    $booking = Booking::factory()->for($this->business)->at('2027-04-06 12:30 UTC', '2027-04-06 13:15 UTC')->create(['staff_id' => $this->priya->id]);
    Booking::factory()->for($this->business)->at('2027-04-06 12:30 UTC', '2027-04-06 13:15 UTC')->create(['staff_id' => $this->sam->id]);
    Booking::factory()->for($this->business)->at('2027-04-06 12:00 UTC', '2027-04-06 12:30 UTC')->create(['staff_id' => $this->priya->id, 'status' => BookingStatus::Cancelled]);

    $this->actingAs($this->owner)
        ->post(route('calendar.blocks.store'), block())
        ->assertInertiaFlash('toast.type', 'warning')
        ->assertInertiaFlash('toast.message', 'Priya is blocked out from 1:00 pm to 2:00 pm. 1 booking is still in that time: move or cancel them if needed.');

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it('lets an owner close the whole business, counting everyone booked', function () {
    Booking::factory()->for($this->business)->at('2027-04-06 12:30 UTC', '2027-04-06 13:15 UTC')->create(['staff_id' => $this->priya->id]);
    Booking::factory()->for($this->business)->at('2027-04-06 12:30 UTC', '2027-04-06 13:15 UTC')->create(['staff_id' => $this->sam->id]);

    $this->actingAs($this->owner)
        ->post(route('calendar.blocks.store'), block(['staff_id' => null, 'reason' => 'Staff training']))
        ->assertInertiaFlash('toast.message', 'Glow Studio is closed from 1:00 pm to 2:00 pm. 2 bookings are still in that time: move or cancel them if needed.');

    expect(TimeOff::sole()->staff_id)->toBeNull();
});

it('blocks out a whole day', function () {
    $this->actingAs($this->owner)->post(route('calendar.blocks.store'), block(['all_day' => true, 'starts_time' => null, 'ends_time' => null]));

    expect(TimeOff::sole()->starts_at->toIso8601ZuluString())->toBe('2027-04-05T23:00:00Z')
        ->and(TimeOff::sole()->ends_at->toIso8601ZuluString())->toBe('2027-04-06T23:00:00Z');
});

it('needs the end after the start', function () {
    $this->actingAs($this->owner)
        ->post(route('calendar.blocks.store'), block(['ends_time' => '12:00']))
        ->assertSessionHasErrors('ends_time');

    expect(TimeOff::count())->toBe(0);
});

it('stops online booking offering the blocked time', function () {
    $service = Service::factory()->for($this->business)->create(['duration_minutes' => 30, 'buffer_after_minutes' => 0]);
    $service->staff()->attach($this->priya);
    $hour = new WorkingHour(['staff_id' => $this->priya->id, 'weekday' => 2, 'starts_at' => '09:00', 'ends_at' => '17:00']);
    $hour->business_id = $this->business->id;
    $hour->save();

    $times = fn () => $this->getJson(route('bookings.times', ['service' => $service->id, 'staff' => $this->priya->id, 'date' => '2027-04-06']))->json('times');

    $this->actingAs($this->owner);
    expect($times())->toContain('2027-04-06T12:00:00Z');

    $this->post(route('calendar.blocks.store'), block());

    expect($times())->not->toContain('2027-04-06T12:00:00Z')
        ->not->toContain('2027-04-06T12:30:00Z')
        ->toContain('2027-04-06T13:00:00Z');
});

it('lets staff block out their own time, and only theirs', function () {
    $this->actingAs($this->priyaUser)->post(route('calendar.blocks.store'), block())->assertRedirect();
    $this->actingAs($this->priyaUser)->post(route('calendar.blocks.store'), block(['staff_id' => $this->sam->id]))->assertForbidden();
    $this->actingAs($this->priyaUser)->post(route('calendar.blocks.store'), block(['staff_id' => null]))->assertForbidden();

    expect(TimeOff::pluck('staff_id')->all())->toBe([$this->priya->id]);
});

it('frees the time up again', function () {
    $off = TimeOff::factory()->for($this->business)->create(['staff_id' => $this->sam->id]);

    $this->actingAs($this->owner)
        ->from(route('calendar'))
        ->delete(route('calendar.blocks.destroy', $off))
        ->assertRedirect(route('calendar'))
        ->assertInertiaFlash('toast.message', 'That time is free again.');

    expect(TimeOff::count())->toBe(0);
});

it('lets staff free up only their own time', function () {
    $mine = TimeOff::factory()->for($this->business)->create(['staff_id' => $this->priya->id]);
    $sams = TimeOff::factory()->for($this->business)->create(['staff_id' => $this->sam->id]);
    $closed = TimeOff::factory()->for($this->business)->create(['staff_id' => null]);

    $this->actingAs($this->priyaUser)->delete(route('calendar.blocks.destroy', $sams))->assertForbidden();
    $this->actingAs($this->priyaUser)->delete(route('calendar.blocks.destroy', $closed))->assertForbidden();
    $this->actingAs($this->priyaUser)->delete(route('calendar.blocks.destroy', $mine))->assertRedirect();

    expect(TimeOff::pluck('id')->sort()->values()->all())->toBe(collect([$sams->id, $closed->id])->sort()->values()->all());
});

it("can't touch another business's time off", function () {
    $theirs = TimeOff::factory()->for(Business::factory())->create();

    $this->actingAs($this->owner)->delete(route('calendar.blocks.destroy', $theirs->id))->assertNotFound();

    expect(TimeOff::withoutGlobalScopes()->whereKey($theirs->id)->exists())->toBeTrue();
});

it('tells the calendar who can free up which time, and who can close the business', function () {
    $mine = TimeOff::factory()->for($this->business)->create(['staff_id' => $this->priya->id, 'starts_at' => '2027-04-06 12:00', 'ends_at' => '2027-04-06 13:00']);
    $sams = TimeOff::factory()->for($this->business)->create(['staff_id' => $this->sam->id, 'starts_at' => '2027-04-06 12:00', 'ends_at' => '2027-04-06 13:00']);

    $removable = fn (User $user) => collect($this->actingAs($user)->get(route('calendar', ['date' => '2027-04-06']))->viewData('page')['props']['timeOff'])
        ->mapWithKeys(fn (array $entry) => [$entry['id'] => $entry['can_remove']])
        ->all();

    expect($removable($this->owner))->toBe([$mine->id => true, $sams->id => true])
        ->and($removable($this->priyaUser))->toBe([$mine->id => true, $sams->id => false]);

    $this->actingAs($this->owner)->get(route('calendar'))->assertInertia(fn (Assert $page) => $page->where('newBooking.can_close', true));
    $this->actingAs($this->priyaUser)->get(route('calendar'))->assertInertia(fn (Assert $page) => $page->where('newBooking.can_close', false));
});
