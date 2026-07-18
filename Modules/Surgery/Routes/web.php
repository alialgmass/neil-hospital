<?php

use Illuminate\Support\Facades\Route;
use Modules\Surgery\Controllers\OrRoomController;
use Modules\Surgery\Controllers\SurgeryController;

Route::middleware(['auth', 'verified'])->group(function () {
    // OR Room helpers (JSON)
    Route::get('/or-rooms', [OrRoomController::class, 'index'])
        ->middleware('can:surgery.view')
        ->name('or-rooms.index');

    Route::get('/or-rooms/available-beds', [OrRoomController::class, 'availableBeds'])
        ->middleware('can:surgery.view')
        ->name('or-rooms.available-beds');

    // Surgery
    Route::prefix('surgery')->name('surgery.')->group(function () {
        Route::get('/', [SurgeryController::class, 'index'])
            ->middleware('can:surgery.view')
            ->name('index');

        Route::post('/', [SurgeryController::class, 'store'])
            ->middleware('can:surgery.write')
            ->name('store');

        Route::post('/{id}/report', [SurgeryController::class, 'report'])
            ->middleware('can:surgery.write')
            ->name('report');

        Route::post('/{id}/supplies', [SurgeryController::class, 'supplies'])
            ->middleware('can:surgery.write')
            ->name('supplies');

        Route::patch('/{id}/status', [SurgeryController::class, 'updateStatus'])
            ->middleware('can:surgery.write')
            ->name('status');
    });

    // Lasik (same controller, dept filtered)
    Route::prefix('lasik')->name('lasik.')->group(function () {
        Route::get('/', [SurgeryController::class, 'index'])
            ->middleware('can:lasik.view')
            ->name('index');

        Route::post('/', [SurgeryController::class, 'store'])
            ->middleware('can:lasik.write')
            ->name('store');

        Route::post('/{id}/report', [SurgeryController::class, 'report'])
            ->middleware('can:lasik.write')
            ->name('report');

        Route::post('/{id}/supplies', [SurgeryController::class, 'supplies'])
            ->middleware('can:lasik.write')
            ->name('supplies');

        Route::patch('/{id}/status', [SurgeryController::class, 'updateStatus'])
            ->middleware('can:lasik.write')
            ->name('status');
    });

    // Laser (same controller, dept filtered)
    Route::prefix('laser')->name('laser.')->group(function () {
        Route::get('/', [SurgeryController::class, 'index'])
            ->middleware('can:laser.view')
            ->name('index');

        Route::post('/', [SurgeryController::class, 'store'])
            ->middleware('can:laser.write')
            ->name('store');

        Route::post('/{id}/report', [SurgeryController::class, 'report'])
            ->middleware('can:laser.write')
            ->name('report');

        Route::patch('/{id}/status', [SurgeryController::class, 'updateStatus'])
            ->middleware('can:laser.write')
            ->name('status');
    });
});
