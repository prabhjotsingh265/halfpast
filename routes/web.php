<?php

use App\Http\Controllers\OnboardingController;
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
    });
});

require __DIR__.'/settings.php';
