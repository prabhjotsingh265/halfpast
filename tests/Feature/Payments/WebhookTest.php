<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Staff;
use App\Payments\CheckoutLinks;
use App\Payments\Gateways\TestGateway;
use App\Tenancy\CurrentBusiness;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

/*
 * Webhooks: the gateway telling us about a payment directly, which is how a
 * booking gets settled when the customer pays and never comes back.
 */

beforeEach(function () {
    Mail::fake();
    $this->business = Business::factory()->create(['slug' => 'glow-studio', 'payment_gateways' => ['test']]);
    $this->booking = Booking::factory()->for($this->business)->held()->create([
        'staff_id' => Staff::factory()->for($this->business)->create()->id,
        'customer_id' => Customer::factory()->for($this->business)->create(['email' => 'ananya@example.com'])->id,
        'deposit' => 2000,
        'currency' => 'GBP',
    ]);
    $this->payment = Payment::factory()->create(['booking_id' => $this->booking->id, 'business_id' => $this->business->id, 'amount' => 2000, 'currency' => 'GBP', 'gateway_order_id' => null]);

    $this->gateway = app(TestGateway::class);
    $this->gateway->begin($this->payment, $this->booking, new CheckoutLinks('https://halfpast.test/back', 'https://halfpast.test/cancel'));
    $this->order = $this->payment->fresh()->gateway_order_id;
});

/** Posts a webhook as the test gateway would, signed unless told otherwise. */
function sendWebhook(string $order, string $type = 'payment.authorized', ?string $signature = null, ?string $body = null): TestResponse
{
    [$signedBody, $signed] = app(TestGateway::class)->signedEvent($order, $type);

    return test()->call('POST', '/webhooks/test', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_TEST_SIGNATURE' => $signature ?? $signed,
    ], content: $body ?? $signedBody);
}

it('settles the booking when the customer pays and never comes back (W2)', function () {
    $this->gateway->pay($this->order);

    sendWebhook($this->order)->assertOk();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Captured)
        ->and(PaymentEvent::sole()->processed_at)->not->toBeNull();

    Mail::assertQueued(BookingConfirmedMail::class, 1);
});

it('acts on a resent webhook only once (W3)', function () {
    $this->gateway->pay($this->order);
    [$body, $signature] = $this->gateway->signedEvent($this->order, 'payment.authorized');

    foreach (range(1, 3) as $attempt) {
        sendWebhook($this->order, signature: $signature, body: $body)->assertOk();
    }

    expect(PaymentEvent::count())->toBe(1)
        ->and(LedgerEntry::count())->toBe(1);
    Mail::assertQueued(BookingConfirmedMail::class, 1);
});

it('refuses a webhook with a forged signature (W4)', function () {
    $this->gateway->pay($this->order);

    sendWebhook($this->order, signature: 'forged')->assertStatus(400);

    expect(PaymentEvent::count())->toBe(0)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::Held);
});

it('acknowledges and ignores a webhook for a payment it does not know (W5)', function () {
    sendWebhook('test_order_not_ours')->assertOk();

    expect(PaymentEvent::sole()->processed_at)->not->toBeNull()
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::Held);
});

it('ignores gateways it has never heard of', function () {
    $this->call('POST', '/webhooks/bitcoin', content: '{}')->assertNotFound();
});

it('settles from the gateway, not from what the webhook says', function () {
    // A webhook claiming payment for an order that was never paid.
    sendWebhook($this->order)->assertOk();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::Held);
});

it('records a declined payment from its webhook', function () {
    $this->gateway->decline($this->order);

    sendWebhook($this->order, 'payment.failed')->assertOk();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::Held);
});

it('settles inside the payment\'s own business, whatever business was current (T1)', function () {
    $other = Business::factory()->create();
    app(CurrentBusiness::class)->set($other);
    $this->gateway->pay($this->order);

    sendWebhook($this->order)->assertOk();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Confirmed)
        ->and(LedgerEntry::withoutGlobalScopes()->sole()->business_id)->toBe($this->business->id);
});

it("doesn't need a CSRF token, since the signature is checked instead", function () {
    $this->gateway->pay($this->order);
    [$body, $signature] = $this->gateway->signedEvent($this->order, 'payment.authorized');

    // A plain POST with no session or token, the way a gateway sends it.
    $this->withoutMiddleware([])->call('POST', '/webhooks/test', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_TEST_SIGNATURE' => $signature], content: $body)
        ->assertOk();
});

it('settles from the test gateway\'s own webhook when the customer closes the tab', function () {
    $checkout = Payment::find($this->payment->id);
    $url = URL::signedRoute('payments.test.show', ['order' => $this->order]);

    $this->post($url, ['action' => 'pay_and_close'])
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('payments/TestCheckout')->where('closed', true));

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($checkout->fresh()->status)->toBe(PaymentStatus::Captured);
});
