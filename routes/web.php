<?php

use App\Http\Controllers\BookingActionsController;
use App\Http\Controllers\Business\BookingRulesController;
use App\Http\Controllers\Business\PaymentMethodsController;
use App\Http\Controllers\CalendarBlockController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EarningsController;
use App\Http\Controllers\NewBookingController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffPhotoController;
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
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('calendar', [CalendarController::class, 'index'])->name('calendar');
        Route::post('calendar/blocks', [CalendarBlockController::class, 'store'])->name('calendar.blocks.store');
        Route::delete('calendar/blocks/{timeOff}', [CalendarBlockController::class, 'destroy'])->name('calendar.blocks.destroy');
        Route::get('bookings/times', [NewBookingController::class, 'times'])->name('bookings.times');
        Route::get('customers/search', [NewBookingController::class, 'customers'])->middleware('throttle:120,1')->name('customers.search');
        Route::post('bookings', [NewBookingController::class, 'store'])->name('bookings.store');
        Route::get('bookings/{booking}/history', [BookingActionsController::class, 'history'])->name('bookings.history');
        Route::post('bookings/{booking}/complete', [BookingActionsController::class, 'complete'])->name('bookings.complete');
        Route::post('bookings/{booking}/no-show', [BookingActionsController::class, 'noShow'])->name('bookings.no-show');
        Route::post('bookings/{booking}/cancel', [BookingActionsController::class, 'cancel'])->middleware('can:manage-business')->name('bookings.cancel');

        // Only owners set up what the business sells and who works there.
        Route::middleware('can:manage-business')->group(function () {
            Route::resource('services', ServiceController::class)->except('show');
            Route::resource('staff', StaffController::class)->except('show');
            Route::post('staff/{staff}/photo', [StaffPhotoController::class, 'store'])->name('staff.photo.store');
            Route::delete('staff/{staff}/photo', [StaffPhotoController::class, 'destroy'])->name('staff.photo.destroy');
            Route::resource('time-off', TimeOffController::class)->only(['index', 'store', 'destroy']);
            Route::resource('customers', CustomerController::class)->only(['index', 'show', 'update', 'destroy']);

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
