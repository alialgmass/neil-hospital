<?php

use Illuminate\Support\Facades\Route;
use Modules\Clinic\Controllers\ClinicController;
use Modules\Clinic\Controllers\MedicalExaminationController;

Route::middleware(['auth', 'verified', 'can:clinic.view'])->prefix('clinic')->name('clinic.')->group(function () {
    Route::get('/', [ClinicController::class, 'index'])->name('index');
    Route::get('/{bookingId}', [ClinicController::class, 'show'])->name('show');
    Route::post('/{bookingId}/sheet', [ClinicController::class, 'storeSheet'])
        ->middleware('can:clinic.write')
        ->name('sheet.store');

    Route::post('/{bookingId}/refer', [ClinicController::class, 'refer'])
        ->middleware('can:clinic.write')
        ->name('refer');
});

Route::middleware(['auth', 'verified'])->prefix('examinations')->name('examinations.')->group(function () {
    Route::get('/booking/{booking}', [MedicalExaminationController::class, 'booking'])
        ->middleware('can:examinations.view')
        ->name('booking');
    Route::post('/booking/{booking}', [MedicalExaminationController::class, 'store'])
        ->middleware('can:examinations.create')
        ->name('store');

    Route::get('/{examination}', [MedicalExaminationController::class, 'show'])
        ->middleware('can:examinations.view')
        ->name('show');
    Route::put('/{examination}', [MedicalExaminationController::class, 'update'])
        ->middleware('can:examinations.update')
        ->name('update');
    Route::delete('/{examination}', [MedicalExaminationController::class, 'destroy'])
        ->middleware('can:examinations.delete')
        ->name('destroy');
    Route::get('/{examination}/print', [MedicalExaminationController::class, 'print'])
        ->middleware('can:examinations.print')
        ->name('print');
});
