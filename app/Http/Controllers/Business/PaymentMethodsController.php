<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Payments\Gateways;
use App\Payments\PaymentGateway;
use App\Tenancy\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Which gateways customers can pay deposits with. Only gateways that are set
 * up and take the business's currency can be switched on.
 */
class PaymentMethodsController extends Controller
{
    public function __construct(private Gateways $gateways) {}

    public function edit(CurrentBusiness $current): Response
    {
        $business = $current->require();

        return Inertia::render('business/PaymentMethods', [
            'currency' => $business->currency,
            'gateways' => array_values(array_map(fn (PaymentGateway $gateway) => [
                'key' => $gateway->key(),
                'label' => $gateway->label(),
                'configured' => $gateway->isConfigured(),
                'supportsCurrency' => $gateway->supports($business->currency),
                'enabled' => in_array($gateway->key(), $business->payment_gateways, true),
            ], $this->gateways->all())),
            'depositServices' => Service::where('deposit_type', '!=', 'none')->where('is_active', true)->count(),
        ]);
    }

    public function update(Request $request, CurrentBusiness $current): RedirectResponse
    {
        $business = $current->require();
        $allowed = array_keys(array_filter(
            $this->gateways->configured(),
            fn (PaymentGateway $gateway) => $gateway->supports($business->currency),
        ));

        $validated = $request->validate([
            'gateways' => ['present', 'array'],
            'gateways.*' => ['string', 'distinct', Rule::in($allowed)],
        ], [
            'gateways.*.in' => "That payment method isn't available for {$business->currency} yet.",
        ]);

        $business->update(['payment_gateways' => array_values((array) $validated['gateways'])]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment methods saved.']);

        return redirect()->route('business.payments.edit');
    }
}
