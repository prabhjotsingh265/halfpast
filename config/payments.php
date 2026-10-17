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
    | Gateways. Keys only ever come from .env. A gateway without its keys
    | isn't offered, even if a business has switched it on.
    */

    'gateways' => [

        // A pretend gateway with its own payment page, for local development
        // and tests. Never available in production.
        'test' => [
            'enabled' => (bool) env('PAYMENTS_TEST_GATEWAY', env('APP_ENV', 'production') !== 'production'),
        ],

    ],

];
