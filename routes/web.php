<?php

use App\Http\Controllers\Business\BookingRulesController;
use App\Http\Controllers\Business\PaymentMethodsController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\EarningsController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\TimeOffController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'marketing/Home')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('start', [OnboardingController::class, 'create'])->name('onboarding.create');
    Route::post('start', [OnboardingController::class, 'store'])->name('onboarding.store');
    Route::get('start/address', [OnboardingController::class, 'checkAddress'])
        ->middleware('throttle:60,1')
        ->name('onboarding.address');

    Route::middleware('has-business')->group(function () {
        Route::inertia('dashboard', 'Dashboard')->name('dashboard');
        Route::get('calendar', [CalendarController::class, 'index'])->name('calendar');

        // Only owners set up what the business sells and who works there.
        Route::middleware('can:manage-business')->group(function () {
            Route::resource('services', ServiceController::class)->except('show');
            Route::resource('staff', StaffController::class)->except('show');
            Route::resource('time-off', TimeOffController::class)->only(['index', 'store', 'destroy']);

            Route::get('earnings', [EarningsController::class, 'index'])->name('earnings.index');

            Route::get('business/booking-rules', [BookingRulesController::class, 'edit'])->name('business.rules.edit');
            Route::put('business/booking-rules', [BookingRulesController::class, 'update'])->name('business.rules.update');
            Route::get('business/payment-methods', [PaymentMethodsController::class, 'edit'])->name('business.payments.edit');
            Route::put('business/payment-methods', [PaymentMethodsController::class, 'update'])->name('business.payments.update');
        });
    });
});

require __DIR__.'/settings.php';

require __DIR__.'/payments.php';

require __DIR__.'/booking.php';
