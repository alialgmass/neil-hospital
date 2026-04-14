<?php

use Illuminate\Support\Facades\Route;
use Modules\Booking\Controllers\BookingController;
use Modules\Booking\Controllers\BookingStatusController;

Route::middleware(['auth', 'verified'])->prefix('booking')->name('booking.')->group(function () {
    Route::get('/', [BookingController::class, 'index'])
        ->middleware('can:booking.view')
        ->name('index');

    Route::post('/', [BookingController::class, 'store'])
        ->middleware('can:booking.create')
        ->name('store');

    Route::put('/{id}', [BookingController::class, 'update'])
        ->middleware('can:booking.edit')
        ->name('update');

    Route::patch('/{id}/status', [BookingStatusController::class, 'update'])
        ->middleware('can:booking.edit')
        ->name('status.update');

    Route::delete('/{id}', [BookingController::class, 'destroy'])
        ->middleware('can:booking.delete')
        ->name('destroy');

    Route::get('/{id}/receipt', [BookingController::class, 'receipt'])
        ->middleware('can:booking.view')
        ->name('receipt');
});
