<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Controllers\InventoryController;
use Modules\Inventory\Controllers\PurchaseInvoiceController;
use Modules\Inventory\Controllers\PurchaseReturnController;
use Modules\Inventory\Controllers\ServiceController;
use Modules\Inventory\Controllers\StockIssueController;
use Modules\Inventory\Controllers\StockPermitController;
use Modules\Inventory\Controllers\StockTakeController;
use Modules\Inventory\Controllers\SupplierController;
use Modules\Inventory\Controllers\SupplyBundleController;

Route::middleware(['auth', 'verified'])->group(function () {
    // Inventory items
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/', [InventoryController::class, 'index'])
            ->middleware('can:inventory.view')
            ->name('index');

        Route::post('/', [InventoryController::class, 'store'])
            ->middleware('can:inventory.write')
            ->name('store');

        Route::put('/{id}', [InventoryController::class, 'update'])
            ->middleware('can:inventory.write')
            ->name('update');
    });

    // Suppliers
    Route::prefix('suppliers')->name('suppliers.')->group(function () {
        Route::get('/', [SupplierController::class, 'index'])
            ->middleware('can:inventory.view')
            ->name('index');

        Route::post('/', [SupplierController::class, 'store'])
            ->middleware('can:inventory.write')
            ->name('store');

        Route::put('/{id}', [SupplierController::class, 'update'])
            ->middleware('can:inventory.write')
            ->name('update');

        Route::post('/{id}/pay', [SupplierController::class, 'pay'])
            ->middleware('can:inventory.write')
            ->name('pay');
    });

    // Purchase invoices
    Route::prefix('purchases')->name('purchases.')->group(function () {
        Route::get('/', [PurchaseInvoiceController::class, 'index'])
            ->middleware('can:inventory.view')
            ->name('index');

        Route::post('/', [PurchaseInvoiceController::class, 'store'])
            ->middleware('can:inventory.write')
            ->name('store');
    });

    // Supply Bundles (بنود المستلزمات)
    Route::prefix('supply-bundles')->name('supply-bundles.')->group(function () {
        Route::get('/', [SupplyBundleController::class, 'index'])
            ->middleware('can:inventory.view')
            ->name('index');

        Route::post('/', [SupplyBundleController::class, 'store'])
            ->middleware('can:inventory.write')
            ->name('store');

        Route::put('/{id}', [SupplyBundleController::class, 'update'])
            ->middleware('can:inventory.write')
            ->name('update');
    });

    // Stock Issue Vouchers — dedicated daily view
    Route::prefix('stock-issue')->name('stock-issue.')->group(function () {
        Route::get('/', [StockIssueController::class, 'index'])
            ->middleware('can:inventory.view')
            ->name('index');

        Route::post('/', [StockIssueController::class, 'store'])
            ->middleware('can:inventory.write')
            ->name('store');
    });

    // Stock permits (issue/add)
    Route::prefix('stock-permits')->name('stock-permits.')->group(function () {
        Route::get('/', [StockPermitController::class, 'index'])
            ->middleware('can:inventory.view')
            ->name('index');

        Route::post('/issue', [StockPermitController::class, 'issue'])
            ->middleware('can:inventory.write')
            ->name('issue');

        Route::post('/add', [StockPermitController::class, 'add'])
            ->middleware('can:inventory.write')
            ->name('add');
    });

    // Purchase Returns
    Route::prefix('purchase-returns')->name('purchase-returns.')->group(function () {
        Route::get('/', [PurchaseReturnController::class, 'index'])
            ->middleware('can:inventory.view')
            ->name('index');

        Route::post('/', [PurchaseReturnController::class, 'store'])
            ->middleware('can:inventory.write')
            ->name('store');
    });

    // Stock Take (Inventory Adjustment)
    Route::prefix('stock-take')->name('stock-take.')->group(function () {
        Route::get('/', [StockTakeController::class, 'index'])
            ->middleware('can:inventory.view')
            ->name('index');

        Route::post('/', [StockTakeController::class, 'store'])
            ->middleware('can:inventory.write')
            ->name('store');
    });

    // Services & Pricing
    Route::prefix('services')->name('services.')->group(function () {
        Route::get('/', [ServiceController::class, 'index'])
            ->middleware('can:services.view')
            ->name('index');

        Route::post('/', [ServiceController::class, 'store'])
            ->middleware('can:services.write')
            ->name('store');

        Route::put('/{id}', [ServiceController::class, 'update'])
            ->middleware('can:services.write')
            ->name('update');

        Route::delete('/{id}', [ServiceController::class, 'destroy'])
            ->middleware('can:services.write')
            ->name('destroy');

        Route::patch('/{id}/status', [ServiceController::class, 'toggleStatus'])
            ->middleware('can:services.write')
            ->name('toggleStatus');

        Route::post('/import', [ServiceController::class, 'import'])
            ->middleware('can:services.write')
            ->name('import');
    });
});
