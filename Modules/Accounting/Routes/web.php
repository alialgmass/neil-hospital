<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Controllers\ChartOfAccountsController;
use Modules\Accounting\Controllers\CostCenterController;
use Modules\Accounting\Controllers\JournalController;
use Modules\Accounting\Controllers\LedgerController;
use Modules\Accounting\Controllers\SalesInvoiceController;
use Modules\Accounting\Controllers\TreasuryController;

Route::middleware(['auth', 'verified'])->group(function () {
    // Treasury
    Route::prefix('treasury')->name('treasury.')->group(function () {
        Route::get('/', [TreasuryController::class, 'index'])
            ->middleware('can:treasury.view')
            ->name('index');

        Route::post('/', [TreasuryController::class, 'store'])
            ->middleware('can:treasury.write')
            ->name('store');
    });

    // Journal
    Route::prefix('journal')->name('journal.')->group(function () {
        Route::get('/', [JournalController::class, 'index'])
            ->middleware('can:journal.view')
            ->name('index');

        Route::post('/', [JournalController::class, 'store'])
            ->middleware('can:journal.write')
            ->name('store');
    });

    // Daily journal alias
    Route::redirect('/daily-journal', '/journal')->name('daily-journal');

    // Chart of Accounts
    Route::prefix('accounts')->name('accounts.')->group(function () {
        Route::get('/', [ChartOfAccountsController::class, 'index'])
            ->middleware('can:journal.view')
            ->name('index');

        Route::post('/', [ChartOfAccountsController::class, 'store'])
            ->middleware('can:journal.write')
            ->name('store');

        Route::put('/{id}', [ChartOfAccountsController::class, 'update'])
            ->middleware('can:journal.write')
            ->name('update');
    });

    // Sales Invoices (Billing)
    Route::prefix('sales-invoices')->name('sales-invoices.')->group(function () {
        Route::get('/booking/{bookingId}', [SalesInvoiceController::class, 'show'])
            ->middleware('can:booking.view')
            ->name('show');

        Route::post('/', [SalesInvoiceController::class, 'store'])
            ->middleware('can:booking.edit')
            ->name('store');
    });

    // Cost Centers
    Route::get('/cost-centers', [CostCenterController::class, 'index'])
        ->middleware('can:journal.view')
        ->name('cost-centers.index');

    // Ledger
    Route::prefix('ledger')->name('ledger.')->group(function () {
        Route::get('/trial-balance', [LedgerController::class, 'trialBalance'])
            ->middleware('can:reports.financial')
            ->name('trial-balance');

        Route::get('/income-statement', [LedgerController::class, 'incomeStatement'])
            ->middleware('can:reports.financial')
            ->name('income-statement');

        Route::get('/balance-sheet', [LedgerController::class, 'balanceSheet'])
            ->middleware('can:reports.financial')
            ->name('balance-sheet');

        Route::get('/account-statement', [LedgerController::class, 'accountStatement'])
            ->middleware('can:reports.financial')
            ->name('account-statement');
    });
});
