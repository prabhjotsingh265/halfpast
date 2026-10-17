<?php

use App\Actions\Booking\ConfirmBooking;
use App\Enums\BookingStatus;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Staff;
use App\Tenancy\CurrentBusiness;
use Illuminate\Support\Facades\Mail;

/*
 * A threading appointment at a salon in Mumbai: 10:30 am India time on
 * Sunday 18 October 2026, which is 05:00 UTC.
 */

beforeEach(function () {
    $this->business = Business::factory()->create(['name' => 'Kesar Beauty Lounge', 'slug' => 'kesar', 'timezone' => 'Asia/Kolkata', 'currency' => 'INR']);
    app(CurrentBusiness::class)->set($this->business);

    $this->customer = Customer::factory()->for($this->business)->create(['name' => 'Meera Nair', 'email' => 'meera@example.com']);
    $this->booking = Booking::factory()->for($this->business)->held()
        ->at('2026-10-18 05:00', '2026-10-18 05:15')
        ->create([
            'staff_id' => Staff::factory()->for($this->business)->create(['name' => 'Aisha'])->id,
            'customer_id' => $this->customer->id,
            'service_name' => 'Eyebrow threading',
            'price' => 15000,
            'deposit' => 0,
            'currency' => 'INR',
        ]);
});

it('emails the customer when their booking is confirmed', function () {
    Mail::fake();

    app(ConfirmBooking::class)->handle($this->booking);

    Mail::assertQueued(BookingConfirmedMail::class, function (BookingConfirmedMail $mail) {
        return $mail->hasTo('meera@example.com')
            && $mail->hasFrom(config('mail.from.address'), 'Kesar Beauty Lounge')
            && $mail->hasSubject('Booked: Eyebrow threading on Sun 18 Oct at 10:30 am');
    });
});

it('writes the email in the business time, with the manage link and a calendar file', function () {
    $mail = new BookingConfirmedMail($this->booking->fresh());

    $mail->assertSeeInHtml("You're booked.", false)
        ->assertSeeInHtml('Sunday 18 October at 10:30 am')
        ->assertSeeInHtml('Kolkata time')
        ->assertSeeInHtml('₹150')
        ->assertSeeInHtml($this->booking->displayCode())
        ->assertSeeInText('View or cancel your booking:')
        ->assertSeeInText('/kesar/booking/'.$this->booking->code.'?signature=');

    [$attachment] = $mail->attachments();

    expect($attachment->as)->toBe('booking.ics')
        ->and($attachment->mime)->toBe('text/calendar; charset=utf-8; method=PUBLISH');

    $ics = $this->booking->fresh()->calendarEvent()->render();

    expect($ics)->toContain('DTSTART:20261018T050000Z')
        ->and($ics)->toContain('SUMMARY:Eyebrow threading at Kesar Beauty Lounge')
        ->and(str_replace("\r\n ", '', $ics))->toContain('With Aisha.');
});

it("doesn't email when there's no address to send to", function () {
    Mail::fake();
    $this->customer->update(['email' => null]);

    app(ConfirmBooking::class)->handle($this->booking);

    Mail::assertNothingQueued();
});

it('offers the calendar file on a signed link', function () {
    $this->booking->update(['status' => BookingStatus::Confirmed]);

    $response = $this->get($this->booking->calendarUrl());

    $response->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
    expect($response->getContent())->toContain('BEGIN:VEVENT');

    $this->get("/kesar/booking/{$this->booking->code}/calendar")->assertForbidden();
});
