<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Payments\GatewayError;
use App\Payments\Gateways\TestGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The test gateway's own payment page, standing in for Stripe's or PayPal's.
 * Only exists outside production.
 */
class TestGatewayController extends Controller
{
    public function __construct(private TestGateway $gateway)
    {
        abort_unless($gateway->isConfigured(), 404);
    }

    public function show(string $order): Response
    {
        try {
            $state = $this->gateway->load($order);
        } catch (GatewayError) {
            abort(404);
        }

        return Inertia::render('payments/TestCheckout', [
            'order' => $order,
            'amount' => $state['amount'],
            'currency' => $state['currency'],
            'state' => $state['state'],
            'cancelUrl' => $state['cancel'],
        ]);
    }

    public function update(Request $request, string $order): RedirectResponse
    {
        try {
            $state = $this->gateway->load($order);
        } catch (GatewayError) {
            abort(404);
        }

        $action = (string) $request->validate(['action' => ['required', 'in:pay,pay_and_capture,decline,cancel']])['action'];

        match ($action) {
            'pay' => $this->gateway->pay($order),
            'pay_and_capture' => $this->gateway->pay($order, capture: true),
            'decline' => $this->gateway->decline($order),
            default => null,
        };

        return redirect()->away($action === 'cancel' ? $state['cancel'] : $state['return']);
    }
}
