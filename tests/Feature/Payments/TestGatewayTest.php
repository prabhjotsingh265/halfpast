<?php

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Payment;
use App\Payments\CheckoutLinks;
use App\Payments\GatewayError;
use App\Payments\Gateways;
use App\Payments\Gateways\TestGateway;
use App\Payments\GatewayState;
use App\Payments\InvalidWebhook;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->gateway = app(TestGateway::class);
    $this->payment = Payment::factory()->create(['booking_id' => Booking::factory()->held()->create(['deposit' => 2000])->id, 'amount' => 2000, 'currency' => 'GBP', 'gateway_order_id' => null]);
    $this->checkout = $this->gateway->begin($this->payment, $this->payment->booking, new CheckoutLinks('https://halfpast.test/back', 'https://halfpast.test/cancelled'));
    $this->order = $this->payment->fresh()->gateway_order_id;
});

it('starts a checkout on its own signed payment page', function () {
    expect($this->order)->toStartWith('test_order_')
        ->and($this->checkout->redirectUrl)->toContain('/payments/test/'.$this->order)
        ->and($this->checkout->redirectUrl)->toContain('signature=')
        ->and($this->gateway->status($this->payment->fresh())->state)->toBe(GatewayState::Pending);
});

it('authorises, then captures once', function () {
    $this->gateway->pay($this->order);
    $payment = $this->payment->fresh();

    expect($this->gateway->status($payment)->state)->toBe(GatewayState::Authorized)
        ->and($this->gateway->status($payment)->amount)->toBe(2000)
        ->and($this->gateway->capture($payment)->state)->toBe(GatewayState::Captured)
        ->and($this->gateway->capture($payment)->state)->toBe(GatewayState::Captured);
});

it('lets an authorisation go without charging', function () {
    $this->gateway->pay($this->order);
    $this->gateway->release($this->payment->fresh());

    $status = $this->gateway->status($this->payment->fresh());

    expect($status->state)->toBe(GatewayState::Failed)
        ->and($status->reason)->toBe('released');

    expect(fn () => $this->gateway->capture($this->payment->fresh()))->toThrow(GatewayError::class);
});

it('refunds once per key and never more than was taken', function () {
    $this->gateway->pay($this->order, capture: true);
    $payment = $this->payment->fresh();

    $first = $this->gateway->refund($payment, 1500, 'refund-1');

    expect($this->gateway->refund($payment, 1500, 'refund-1'))->toBe($first)
        ->and($this->gateway->load($this->order)['refunded'])->toBe(1500);

    expect(fn () => $this->gateway->refund($payment, 600, 'refund-2'))->toThrow(GatewayError::class);
});

it('can be told to fail, like a gateway that is down', function () {
    $this->gateway->pay($this->order);
    TestGateway::failNext('capture');

    expect(fn () => $this->gateway->capture($this->payment->fresh()))->toThrow(GatewayError::class)
        ->and($this->gateway->capture($this->payment->fresh())->state)->toBe(GatewayState::Captured);
});

it('only believes webhooks with the right signature', function () {
    [$body, $signature] = $this->gateway->signedEvent($this->order, 'payment.authorized');

    $event = $this->gateway->webhook(Request::create('/', 'POST', server: ['HTTP_X_TEST_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], content: $body));

    expect($event->orderId)->toBe($this->order)
        ->and($event->type)->toBe('payment.authorized');

    expect(fn () => $this->gateway->webhook(Request::create('/', 'POST', server: ['HTTP_X_TEST_SIGNATURE' => 'forged', 'CONTENT_TYPE' => 'application/json'], content: $body)))
        ->toThrow(InvalidWebhook::class);
});

it('has a payment page that sends the customer back', function () {
    $this->get($this->checkout->redirectUrl)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('payments/TestCheckout')->where('amount', 2000)->where('state', 'pending'));

    $this->post($this->checkout->redirectUrl, ['action' => 'pay'])->assertRedirect('https://halfpast.test/back');

    // Its webhook arrives (straight away in tests) and settles the payment.
    expect($this->gateway->load($this->order)['state'])->toBe('captured')
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Captured);

    // Unsigned, the page doesn't exist.
    $this->get('/payments/test/'.$this->order)->assertForbidden();
});

it('sends a customer who cancels to the cancel address', function () {
    $this->post($this->checkout->redirectUrl, ['action' => 'cancel'])->assertRedirect('https://halfpast.test/cancelled');

    expect($this->gateway->load($this->order)['state'])->toBe('pending');
});

it('is never offered in production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect($this->gateway->isConfigured())->toBeFalse();
});

it('offers only the gateways a business switched on, that are set up and take its currency', function () {
    $business = Business::factory()->create(['currency' => 'INR', 'payment_gateways' => []]);

    expect(app(Gateways::class)->for($business))->toBe([])
        ->and(app(Gateways::class)->canTakeDeposits($business))->toBeFalse();

    $business->update(['payment_gateways' => ['test']]);

    expect(array_keys(app(Gateways::class)->for($business->fresh())))->toBe(['test']);

    config(['payments.gateways.test.enabled' => false]);

    expect(app(Gateways::class)->for($business->fresh()))->toBe([]);
});
