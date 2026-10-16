<?php

use App\Http\Controllers\BookingPageController;
use Illuminate\Support\Facades\Route;

/*
| Public booking pages, at /{business}. Loaded last, so every fixed address
| the app has (login, start, services...) is matched before this one. The
| booking address rule stops a business from taking any of those names.
*/

Route::middleware('business-from-address')
    ->prefix('{business}')
    ->where(['business' => '[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])'])
    ->group(function () {
        Route::get('/', [BookingPageController::class, 'show'])->name('booking.show');
    });
