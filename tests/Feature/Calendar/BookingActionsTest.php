<?php

use App\Actions\Payments\SettlePayment;
use App\Enums\BookingStatus;
use App\Enums\BusinessRole;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Mail\BookingCancelledMail;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Staff;
use App\Models\User;
use App\Payments\CheckoutLinks;
use App\Payments\Gateways\TestGateway;
use App\Tenancy\CurrentBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

/*
 * Tuesday 27 October 2026, 10:00 in London. Priya's 09:00 appointment has
 * started; Ananya's colour on Friday at 10:00 is still to come, with its
 * £20 deposit paid. Free cancellation ends 24 hours before.
 */

beforeEach(function () {
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-27 10:00', 'UTC'));

    $this->owner = User::factory()->create();
    $this->business = Business::factory()->ownedBy($this->owner)->create(['timezone' => 'Europe/London', 'cancellation_window_hours' => 24, 'payment_gateways' => ['test']]);
    app(CurrentBusiness::class)->set($this->business);

    $this->priyaUser = User::factory()->create();
    $this->business->addMember($this->priyaUser, BusinessRole::Staff);
    $this->priya = Staff::factory()->for($this->business)->create(['name' => 'Priya', 'user_id' => $this->priyaUser->id]);
    $this->sam = Staff::factory()->for($this->business)->create(['name' => 'Sam']);

    $this->started = Booking::factory()->for($this->business)->at('2026-10-27 09:00', '2026-10-27 10:30')->create([
        'staff_id' => $this->priya->id,
        'customer_id' => Customer::factory()->for($this->business)->create()->id,
    ]);

    $this->upcoming = Booking::factory()->for($this->business)->held(60)->at('2026-10-30 10:00', '2026-10-30 11:00')->create([
        'staff_id' => $this->sam->id,
        'customer_id' => Customer::factory()->for($this->business)->create(['name' => 'Ananya Iyer', 'email' => 'ananya@example.com'])->id,
        'deposit' => 2000,
        'currency' => 'GBP',
    ]);

    // The deposit paid through checkout, as it would be.
    $payment = Payment::factory()->create(['booking_id' => $this->upcoming->id, 'business_id' => $this->business->id, 'amount' => 2000, 'currency' => 'GBP', 'gateway_order_id' => null]);
    $gateway = app(TestGateway::class);
    $gateway->begin($payment, $this->upcoming, new CheckoutLinks('https://halfpast.test/back', 'https://halfpast.test/cancel'));
    $gateway->pay($payment->fresh()->gateway_order_id);
    app(SettlePayment::class)->handle($payment->fresh());
    $this->payment = $payment->fresh();
    Mail::fake();
});

it('marks an appointment that has started as completed, or a no-show', function () {
    $this->actingAs($this->owner)
        ->from(route('calendar'))
        ->post(route('bookings.complete', $this->started))
        ->assertRedirect(route('calendar'))
        ->assertInertiaFlash('toast.message', 'Marked as completed.');

    expect($this->started->fresh()->status)->toBe(BookingStatus::Completed);

    // Marked wrongly: it can be changed.
    $this->post(route('bookings.no-show', $this->started))->assertInertiaFlash('toast.message', 'Marked as a no-show.');

    expect($this->started->fresh()->status)->toBe(BookingStatus::NoShow);
});

it("won't mark an appointment that hasn't started", function () {
    $this->upcoming->refresh();

    $this->actingAs($this->owner)
        ->post(route('bookings.complete', $this->upcoming))
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', 'You can say how it went once the appointment has started.');

    expect($this->upcoming->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it('only marks bookings that went ahead', function (BookingStatus $status) {
    $this->started->update(['status' => $status]);

    $this->actingAs($this->owner)
        ->post(route('bookings.no-show', $this->started))
        ->assertInertiaFlash('toast.type', 'error');

    expect($this->started->fresh()->status)->toBe($status);
})->with([BookingStatus::Cancelled, BookingStatus::Expired, BookingStatus::Held]);

it('lets staff mark their own appointments, and only theirs', function () {
    $samsStarted = Booking::factory()->for($this->business)->at('2026-10-27 09:00', '2026-10-27 09:30')->create(['staff_id' => $this->sam->id]);

    $this->actingAs($this->priyaUser)->post(route('bookings.complete', $this->started))->assertRedirect();
    $this->actingAs($this->priyaUser)->post(route('bookings.complete', $samsStarted))->assertForbidden();

    expect($this->started->fresh()->status)->toBe(BookingStatus::Completed)
        ->and($samsStarted->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it('lets an owner cancel and refund the deposit', function () {
    $this->actingAs($this->owner)
        ->from(route('calendar'))
        ->post(route('bookings.cancel', $this->upcoming), ['refund' => true, 'tell_customer' => true])
        ->assertRedirect(route('calendar'))
        ->assertInertiaFlash('toast.message', 'Booking cancelled. £20 is on its way back to Ananya Iyer.');

    $booking = $this->upcoming->fresh();
    expect($booking->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->cancelled_by)->toBe('business')
        ->and($booking->refund_due)->toBe(2000)
        ->and(Refund::sole()->reason)->toBe('business_cancelled')
        ->and(Refund::sole()->status)->toBe(RefundStatus::Succeeded)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and(LedgerEntry::orderBy('id')->pluck('amount')->all())->toBe([2000, -2000]);

    Mail::assertQueued(BookingCancelledMail::class, fn ($mail) => $mail->hasTo('ananya@example.com'));

    (new BookingCancelledMail($booking))
        ->assertSeeInText('had to cancel it. Sorry for the trouble.')
        ->assertSeeInText('£20 deposit is being refunded');
});

it('lets an owner cancel and keep the deposit, and words the email to match', function () {
    $this->actingAs($this->owner)
        ->post(route('bookings.cancel', $this->upcoming), ['refund' => false, 'tell_customer' => true])
        ->assertInertiaFlash('toast.message', 'Booking cancelled.');

    $booking = $this->upcoming->fresh();
    expect($booking->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->refund_due)->toBe(0)
        ->and(Refund::count())->toBe(0)
        ->and((int) LedgerEntry::sum('amount'))->toBe(2000);

    (new BookingCancelledMail($booking))
        ->assertSeeInText('has cancelled it.')
        ->assertDontSeeInText('Sorry for the trouble')
        ->assertDontSeeInText('less than 24 hours')
        ->assertSeeInText('has kept the £20 deposit');
});

it("doesn't email the customer when asked not to", function () {
    $this->actingAs($this->owner)->post(route('bookings.cancel', $this->upcoming), ['refund' => true, 'tell_customer' => false]);

    expect($this->upcoming->fresh()->status)->toBe(BookingStatus::Cancelled);
    Mail::assertNothingQueued();
    Mail::assertNothingSent();
});

it('needs both choices to cancel', function () {
    $this->actingAs($this->owner)
        ->post(route('bookings.cancel', $this->upcoming), ['refund' => 'maybe'])
        ->assertSessionHasErrors(['refund', 'tell_customer']);

    expect($this->upcoming->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it("won't cancel an appointment that has started", function () {
    $this->actingAs($this->owner)
        ->post(route('bookings.cancel', $this->started), ['refund' => true, 'tell_customer' => true])
        ->assertInertiaFlash('toast.type', 'error');

    expect($this->started->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it('keeps cancelling to owners', function () {
    $this->actingAs($this->priyaUser)
        ->post(route('bookings.cancel', $this->upcoming), ['refund' => true, 'tell_customer' => true])
        ->assertForbidden();

    expect($this->upcoming->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it("can't touch another business's bookings", function (string $action) {
    $other = Business::factory()->create();
    $theirs = Booking::factory()->for($other)->at('2026-10-27 09:00', '2026-10-27 10:00')->create();

    $this->actingAs($this->owner)
        ->post(route($action, $theirs->code), ['refund' => true, 'tell_customer' => true])
        ->assertNotFound();

    expect($theirs->fresh()->status)->toBe(BookingStatus::Confirmed);
})->with(['bookings.complete', 'bookings.no-show', 'bookings.cancel']);

it('tells the calendar what each person can do with each booking', function () {
    $samsStarted = Booking::factory()->for($this->business)->at('2026-10-27 09:00', '2026-10-27 09:30')->create(['staff_id' => $this->sam->id]);
    $can = fn (User $user) => collect($this->actingAs($user)
        ->get(route('calendar', ['view' => 'week', 'date' => '2026-10-27']))
        ->viewData('page')['props']['bookings'])
        ->mapWithKeys(fn (array $b) => [$b['code'] => $b['can']]);

    $owner = $can($this->owner);
    expect($owner[$this->started->code])->toBe(['mark' => true, 'cancel' => false])
        ->and($owner[$this->upcoming->code])->toBe(['mark' => false, 'cancel' => true])
        ->and($owner[$samsStarted->code])->toBe(['mark' => true, 'cancel' => false]);

    $priya = $can($this->priyaUser);
    expect($priya[$this->started->code])->toBe(['mark' => true, 'cancel' => false])
        ->and($priya[$this->upcoming->code])->toBe(['mark' => false, 'cancel' => false])
        ->and($priya[$samsStarted->code])->toBe(['mark' => false, 'cancel' => false]);
});

it('needs a login', function () {
    $this->post(route('bookings.complete', $this->started))->assertRedirect(route('login'));
});
