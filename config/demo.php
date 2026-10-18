<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Demo mode
    |--------------------------------------------------------------------------
    |
    | Three demo businesses anyone can log into with one click, rebuilt every
    | night so whatever visitors change is put back. Their payments go
    | through the built-in test gateway, so no real money is ever involved.
    |
    */

    'enabled' => (bool) env('DEMO_MODE', false),

    // When the nightly rebuild runs, in UTC.
    'reset_at' => env('DEMO_RESET_AT', '03:00'),

    // How many days of history and of upcoming bookings to fill in.
    'days_before' => 14,
    'days_after' => 14,
];
