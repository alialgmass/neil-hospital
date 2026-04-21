<?php

use Illuminate\Support\Facades\Route;
use Modules\Insurance\Controllers\InsuranceClaimController;
use Modules\Insurance\Controllers\InsuranceCompanyController;
use Modules\Insurance\Controllers\PriceListController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('insurance')->name('insurance.')->group(function () {
        Route::middleware('can:insurance.view')->group(function () {
            Route::get('/', [InsuranceCompanyController::class, 'index'])->name('index');
            Route::get('/price-lists', [PriceListController::class, 'index'])->name('price-lists.index');
        });

        Route::middleware('can:insurance.write')->group(function () {
            Route::post('/', [InsuranceCompanyController::class, 'store'])->name('store');
            Route::put('/{id}', [InsuranceCompanyController::class, 'update'])->name('update');
            Route::delete('/{id}', [InsuranceCompanyController::class, 'destroy'])->name('destroy');

            Route::post('/price-lists', [PriceListController::class, 'store'])->name('price-lists.store');

            Route::prefix('claims')->name('claims.')->group(function () {
                Route::post('/', [InsuranceClaimController::class, 'store'])->name('store');
                Route::put('/{id}', [InsuranceClaimController::class, 'update'])->name('update');
                Route::delete('/{id}', [InsuranceClaimController::class, 'destroy'])->name('destroy');
            });
        });
    });
});
