<?php

use App\Actions\Booking\ConfirmBooking;
use App\Enums\BookingStatus;
use App\Enums\BusinessRole;
use App\Events\CalendarChanged;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Staff;
use App\Models\TimeOff;
use App\Models\User;
use App\Tenancy\CurrentBusiness;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;

/*
 * A salon in Kolkata (UTC+5:30): 20:00 UTC is already the next day there.
 */

beforeEach(function () {
    $this->business = Business::factory()->create(['timezone' => 'Asia/Kolkata']);
    app(CurrentBusiness::class)->set($this->business);
    $this->staff = Staff::factory()->for($this->business)->create();
    Event::fake([CalendarChanged::class]);
});

function changed(): array
{
    $events = [];
    Event::assertDispatched(CalendarChanged::class, function (CalendarChanged $event) use (&$events) {
        $events[] = [$event->businessId, $event->from, $event->to];

        return true;
    });

    return $events;
}

it('tells calendars about a new booking, on its local day', function () {
    Booking::factory()->for($this->business)->at('2026-10-20 20:00', '2026-10-20 21:00')->create(['staff_id' => $this->staff->id]);

    expect(changed())->toBe([[$this->business->id, '2026-10-21', '2026-10-21']]);
});

it('covers the day a booking left as well as the day it moved to', function () {
    $booking = Booking::factory()->for($this->business)->at('2026-10-20 05:00', '2026-10-20 06:00')->create(['staff_id' => $this->staff->id]);
    Event::fake([CalendarChanged::class]);

    $booking->update(['starts_at' => '2026-10-23 05:00', 'ends_at' => '2026-10-23 06:00', 'blocked_until' => '2026-10-23 06:00']);

    expect(changed())->toBe([[$this->business->id, '2026-10-20', '2026-10-23']]);
});

it('stays quiet when nothing the calendar shows has changed', function () {
    $booking = Booking::factory()->for($this->business)->at('2026-10-20 05:00', '2026-10-20 06:00')->create(['staff_id' => $this->staff->id]);
    Event::fake([CalendarChanged::class]);

    $booking->update(['refund_due' => 0, 'price' => $booking->price + 100]);

    Event::assertNotDispatched(CalendarChanged::class);
});

it('tells calendars when a booking is confirmed, cancelled or marked', function () {
    $booking = Booking::factory()->for($this->business)->held()->at('2026-10-20 05:00', '2026-10-20 06:00')->create(['staff_id' => $this->staff->id]);
    Event::fake([CalendarChanged::class]);

    app(ConfirmBooking::class)->handle($booking);
    Event::assertDispatched(CalendarChanged::class);

    Event::fake([CalendarChanged::class]);
    $booking->update(['status' => BookingStatus::NoShow]);
    Event::assertDispatched(CalendarChanged::class);
});

it('tells calendars about time off, and a day ending at midnight stays that day', function () {
    // All of 20 and 21 October in Kolkata.
    $off = TimeOff::factory()->for($this->business)->create([
        'staff_id' => $this->staff->id,
        'starts_at' => '2026-10-19 18:30',
        'ends_at' => '2026-10-21 18:30',
    ]);

    expect(changed())->toBe([[$this->business->id, '2026-10-20', '2026-10-21']]);

    Event::fake([CalendarChanged::class]);
    $off->delete();

    expect(changed())->toBe([[$this->business->id, '2026-10-20', '2026-10-21']]);
});

it("sends only the days, on the business's own private channel", function () {
    $event = new CalendarChanged($this->business->id, '2026-10-20', '2026-10-21');

    expect($event->broadcastOn()->name)->toBe("private-business.{$this->business->id}.calendar")
        ->and($event->broadcastAs())->toBe('calendar.changed')
        ->and($event->broadcastWith())->toBe(['from' => '2026-10-20', 'to' => '2026-10-21']);
});

it('lets only people who work there listen', function () {
    $listen = Broadcast::driver()->getChannels()['business.{businessId}.calendar'];

    $member = User::factory()->create();
    $this->business->addMember($member, BusinessRole::Staff);
    $outsider = User::factory()->create();
    Business::factory()->ownedBy($outsider)->create();

    expect($listen($member, $this->business->id))->toBeTrue()
        ->and($listen($outsider, $this->business->id))->toBeFalse();
});
