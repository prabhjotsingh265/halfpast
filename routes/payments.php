<?php

use App\Http\Controllers\Payments\TestGatewayController;
use App\Http\Controllers\Payments\WebhookController;
use Illuminate\Support\Facades\Route;

// The test gateway's payment page (local development and tests only).
Route::middleware('signed')->group(function () {
    Route::get('payments/test/{order}', [TestGatewayController::class, 'show'])->name('payments.test.show');
    Route::post('payments/test/{order}', [TestGatewayController::class, 'update'])->name('payments.test.update');
});

// Gateways tell us about payments here. Signatures are checked inside, so
// these skip the CSRF check (set in bootstrap/app.php).
Route::post('webhooks/{gateway}', WebhookController::class)
    ->where('gateway', '[a-z]+')
    ->middleware('throttle:600,1')
    ->name('payments.webhook');
