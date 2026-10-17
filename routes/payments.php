<?php

use App\Http\Controllers\Payments\TestGatewayController;
use Illuminate\Support\Facades\Route;

// The test gateway's payment page (local development and tests only).
Route::middleware('signed')->group(function () {
    Route::get('payments/test/{order}', [TestGatewayController::class, 'show'])->name('payments.test.show');
    Route::post('payments/test/{order}', [TestGatewayController::class, 'update'])->name('payments.test.update');
});
