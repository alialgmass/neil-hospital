<?php

use Illuminate\Support\Facades\Route;
use Modules\Doctor\Controllers\DoctorClaimsController;
use Modules\Doctor\Controllers\DoctorController;
use Modules\Doctor\Controllers\DoctorPaymentController;
use Modules\Doctor\Controllers\DoctorShiftController;

Route::middleware(['auth', 'verified'])->group(function () {
    // Doctors management
    Route::prefix('doctors')->name('doctors.')->group(function () {
        Route::get('/', [DoctorController::class, 'index'])
            ->middleware('can:doctors.view')
            ->name('index');

        Route::post('/', [DoctorController::class, 'store'])
            ->middleware('can:doctors.write')
            ->name('store');

        Route::put('/{id}', [DoctorController::class, 'update'])
            ->middleware('can:doctors.write')
            ->name('update');

        Route::delete('/{id}', [DoctorController::class, 'destroy'])
            ->middleware('can:doctors.delete')
            ->name('destroy');
    });

    // Doctor claims
    Route::prefix('dr-claims')->name('dr-claims.')->group(function () {
        Route::get('/', [DoctorClaimsController::class, 'index'])
            ->middleware('can:drpayments.view')
            ->name('index');

        Route::get('/calculate', [DoctorClaimsController::class, 'calculate'])
            ->middleware('can:drpayments.view')
            ->name('calculate');

        Route::post('/pay', [DoctorClaimsController::class, 'pay'])
            ->middleware('can:drpayments.write')
            ->name('pay');
    });

    // Doctor payments history
    Route::get('/dr-payments', [DoctorPaymentController::class, 'index'])
        ->middleware('can:drpayments.view')
        ->name('dr-payments.index');

    // Doctor shifts
    Route::prefix('doctor-shifts')->name('doctor-shifts.')->group(function () {
        Route::get('/', [DoctorShiftController::class, 'index'])
            ->middleware('can:doctors.view')
            ->name('index');

        Route::post('/', [DoctorShiftController::class, 'store'])
            ->middleware('can:doctors.write')
            ->name('store');

        Route::get('/{id}', [DoctorShiftController::class, 'show'])
            ->middleware('can:doctors.view')
            ->name('show');

        Route::patch('/{id}/close', [DoctorShiftController::class, 'close'])
            ->middleware('can:doctors.write')
            ->name('close');

        Route::post('/{id}/handover', [DoctorShiftController::class, 'handover'])
            ->middleware('can:doctors.write')
            ->name('handover');
    });
});
