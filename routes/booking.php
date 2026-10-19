<?php

use App\Http\Controllers\BookingPageController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HoldController;
use App\Http\Controllers\ManageBookingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ShareImageController;
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
        Route::get('share.png', [ShareImageController::class, 'business'])->name('booking.share');

        // Generous for a person, tight enough that a script can't hold a whole day.
        Route::post('hold', [HoldController::class, 'store'])->middleware('throttle:20,1')->name('booking.hold');
        Route::delete('hold/{booking}', [HoldController::class, 'destroy'])->name('booking.release');
        Route::get('checkout/{booking}', [CheckoutController::class, 'show'])->name('booking.checkout');
        Route::post('checkout/{booking}', [CheckoutController::class, 'store'])->middleware('throttle:20,1')->name('booking.details');
        Route::post('checkout/{booking}/pay', [PaymentController::class, 'store'])->middleware('throttle:20,1')->name('booking.pay');
        Route::get('checkout/{booking}/payments/{payment}', [PaymentController::class, 'show'])->scopeBindings()->name('booking.payment.return');

        // The customer's link from their confirmation email.
        Route::get('booking/{booking}', [ManageBookingController::class, 'show'])->middleware('signed')->name('booking.manage');
        Route::get('booking/{booking}/calendar', [ManageBookingController::class, 'calendar'])->middleware('signed')->name('booking.calendar');
        Route::post('booking/{booking}/cancel', [ManageBookingController::class, 'cancel'])->middleware(['signed', 'throttle:10,1'])->name('booking.cancel');
    });
