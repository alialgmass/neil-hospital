<?php

use Illuminate\Support\Facades\Route;
use Modules\Admin\Controllers\ActivityLogController;
use Modules\Admin\Controllers\ArchiveController;
use Modules\Admin\Controllers\InsuranceController;
use Modules\Admin\Controllers\RoleController;
use Modules\Admin\Controllers\ServicesController;
use Modules\Admin\Controllers\SettingsController;
use Modules\Admin\Controllers\UserManagementController;

Route::middleware(['auth', 'verified'])->group(function () {
    // Services & pricing
    Route::prefix('services')->name('services.')->group(function () {
        Route::get('/', [ServicesController::class, 'index'])
            ->middleware('can:services.view')
            ->name('index');

        Route::post('/', [ServicesController::class, 'store'])
            ->middleware('can:services.write')
            ->name('store');

        Route::put('/{id}', [ServicesController::class, 'update'])
            ->middleware('can:services.write')
            ->name('update');
    });

    // Insurance companies
    Route::prefix('insurance')->name('insurance.')->group(function () {
        Route::get('/', [InsuranceController::class, 'index'])
            ->middleware('can:insurance.view')
            ->name('index');

        Route::post('/', [InsuranceController::class, 'store'])
            ->middleware('can:insurance.write')
            ->name('store');

        Route::put('/{id}', [InsuranceController::class, 'update'])
            ->middleware('can:insurance.write')
            ->name('update');
    });

    // System settings
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])
            ->middleware('can:settings.manage')
            ->name('index');

        Route::post('/', [SettingsController::class, 'update'])
            ->middleware('can:settings.manage')
            ->name('update');
    });

    // Roles & permissions
    Route::prefix('roles')->name('roles.')->group(function () {
        Route::get('/', [RoleController::class, 'index'])
            ->middleware('can:users.manage')
            ->name('index');

        Route::put('/{id}/permissions', [RoleController::class, 'updatePermissions'])
            ->middleware('can:users.manage')
            ->name('update-permissions');
    });

    // Archive
    Route::get('/archive', [ArchiveController::class, 'index'])
        ->middleware('can:reports.clinical')
        ->name('archive');

    Route::post('/archive', [ArchiveController::class, 'store'])
        ->middleware('can:reports.clinical')
        ->name('archive.store');

    Route::post('/archive/{booking}/upload', [ArchiveController::class, 'upload'])
        ->middleware('can:reports.clinical')
        ->name('archive.upload');

    Route::delete('/archive/media/{media}', [ArchiveController::class, 'destroyMedia'])
        ->middleware('can:reports.clinical')
        ->name('archive.media.destroy');

    // Activity log
    Route::get('/activity-log', [ActivityLogController::class, 'index'])
        ->middleware('can:users.manage')
        ->name('activity-log');

    // User management
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])
            ->middleware('can:users.manage')
            ->name('index');

        Route::post('/', [UserManagementController::class, 'store'])
            ->middleware('can:users.manage')
            ->name('store');

        Route::patch('/{id}/role', [UserManagementController::class, 'updateRole'])
            ->middleware('can:users.manage')
            ->name('update-role');
    });
});
