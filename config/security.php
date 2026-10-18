<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Content security policy
    |--------------------------------------------------------------------------
    |
    | Pages may only run scripts from this site (with a fresh nonce on each
    | page) and from the payment gateways, and only talk to this site, the
    | live-updates server and the gateways. Turn it off only to debug.
    |
    */

    'csp' => (bool) env('CSP_ENABLED', true),

    // Where the browser reaches the live-updates server.
    'reverb' => [
        'host' => env('VITE_REVERB_HOST'),
        'port' => env('VITE_REVERB_PORT'),
        'scheme' => env('VITE_REVERB_SCHEME', 'https'),
    ],
];
