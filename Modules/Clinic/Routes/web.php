<?php

use Illuminate\Support\Facades\Route;
use Modules\Clinic\Controllers\ClinicController;

Route::middleware(['auth', 'verified', 'can:clinic.view'])->prefix('clinic')->name('clinic.')->group(function () {
    Route::get('/', [ClinicController::class, 'index'])->name('index');
    Route::get('/{bookingId}', [ClinicController::class, 'show'])->name('show');
    Route::post('/{bookingId}/sheet', [ClinicController::class, 'storeSheet'])
        ->middleware('can:clinic.write')
        ->name('sheet.store');
});
