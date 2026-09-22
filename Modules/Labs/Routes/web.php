<?php

use Illuminate\Support\Facades\Route;
use Modules\Labs\Controllers\LabsController;

Route::middleware(['auth', 'verified'])->prefix('labs')->name('labs.')->group(function () {
    Route::get('/', [LabsController::class, 'index'])
        ->middleware('can:labs.view')
        ->name('index');

    Route::post('/{bookingId}/results', [LabsController::class, 'storeResult'])
        ->middleware('can:labs.write')
        ->name('results.store');

    Route::get('/results/{resultId}/letter', [LabsController::class, 'referralLetter'])
        ->middleware('can:labs.view')
        ->name('results.letter');
});
