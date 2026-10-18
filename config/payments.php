<?php

return [

    /*
    | Starting a payment gives the customer a fresh stretch of time on their
    | hold, so the clock can't run out while they're on the payment page.
    | However often they press "Pay", a hold never lasts longer than the max.
    */

    'payment_window_minutes' => 15,

    'max_hold_minutes' => 45,

    /*
    | Gateways that are planned but not set up yet (PayPal, for now) are shown
    | as "coming soon" on the payment step instead of being left out.
    */

    'show_upcoming' => (bool) env('PAYMENTS_SHOW_UPCOMING', true),

    /*
    | Gateways. Keys only ever come from .env. A gateway without its keys
    | isn't offered, even if a business has switched it on.
    */

    'gateways' => [

        // A pretend gateway with its own payment page, for local development
        // and tests. Never available in production.
        'test' => [
            'enabled' => (bool) env('PAYMENTS_TEST_GATEWAY', env('APP_ENV', 'production') !== 'production'),
        ],

        'stripe' => [
            'key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            // Pinned, so a change on Stripe's side never changes what we get back.
            'api_version' => '2024-06-20',
            'currencies' => ['GBP', 'USD', 'EUR', 'INR', 'AUD', 'CAD'],
        ],

        'razorpay' => [
            'key_id' => env('RAZORPAY_KEY_ID'),
            'key_secret' => env('RAZORPAY_KEY_SECRET'),
            'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
            // Other currencies need international payments switched on in Razorpay.
            'currencies' => array_filter(explode(',', (string) env('RAZORPAY_CURRENCIES', 'INR'))),
        ],

        // Not built yet: it waits for PayPal sandbox keys. Shown as coming soon.
        'paypal' => [
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'secret' => env('PAYPAL_SECRET'),
            'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
            'mode' => env('PAYPAL_MODE', 'sandbox'),
            'currencies' => ['GBP', 'USD', 'EUR', 'AUD', 'CAD'],
        ],

    ],

];
