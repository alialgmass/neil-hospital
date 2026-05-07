<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Modules\Reporting\Controllers\CasesReportController;
use Modules\Reporting\Controllers\DashboardController;
use Modules\Reporting\Controllers\DeptRevenueReportController;
use Modules\Reporting\Controllers\DoctorClaimsReportController;
use Modules\Reporting\Controllers\DoctorPaymentsReportController;
use Modules\Reporting\Controllers\ExpenseAnalysisController;
use Modules\Reporting\Controllers\ExpiryReportController;
use Modules\Reporting\Controllers\InsuranceReportController;
use Modules\Reporting\Controllers\InventoryMovementController;
use Modules\Reporting\Controllers\ProfitLossController;
use Modules\Reporting\Controllers\PurchasePriceReportController;
use Modules\Reporting\Controllers\ReportController;
use Modules\Reporting\Controllers\SurgeriesReportController;

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('can:dashboard')
        ->name('dashboard');

    // Legacy report routes
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::redirect('/', '/reports/index');

        Route::get('/index', fn () => Inertia::render('reports/Index'))
            ->middleware('can:reports.clinical')
            ->name('index');

        Route::get('/daily', [ReportController::class, 'daily'])
            ->middleware('can:reports.clinical')
            ->name('daily');

        Route::get('/income', [ReportController::class, 'income'])
            ->middleware('can:reports.financial')
            ->name('income');

        // Detailed reports
        Route::get('/dept-revenue', DeptRevenueReportController::class)
            ->middleware('can:reports.financial')
            ->name('dept-revenue');

        Route::get('/dept-revenue/export', [DeptRevenueReportController::class, 'export'])
            ->middleware('can:reports.financial')
            ->name('dept-revenue.export');

        Route::get('/cases', CasesReportController::class)
            ->middleware('can:reports.clinical')
            ->name('cases');

        Route::get('/doctor-claims', DoctorClaimsReportController::class)
            ->middleware('can:reports.financial')
            ->name('doctor-claims');

        Route::get('/doctor-claims/export', [DoctorClaimsReportController::class, 'export'])
            ->middleware('can:reports.financial')
            ->name('doctor-claims.export');

        Route::get('/doctor-payments', DoctorPaymentsReportController::class)
            ->middleware('can:reports.financial')
            ->name('doctor-payments');

        Route::get('/insurance', InsuranceReportController::class)
            ->middleware('can:reports.financial')
            ->name('insurance');

        Route::get('/inventory-movement', InventoryMovementController::class)
            ->middleware('can:inventory.view')
            ->name('inventory-movement');

        Route::get('/purchase-prices', PurchasePriceReportController::class)
            ->middleware('can:inventory.view')
            ->name('purchase-prices');

        Route::get('/profit-loss', ProfitLossController::class)
            ->middleware('can:reports.financial')
            ->name('profit-loss');

        Route::get('/profit-loss/export', [ProfitLossController::class, 'export'])
            ->middleware('can:reports.financial')
            ->name('profit-loss.export');

        Route::get('/expense-analysis', ExpenseAnalysisController::class)
            ->middleware('can:reports.financial')
            ->name('expense-analysis');

        Route::get('/surgeries', SurgeriesReportController::class)
            ->middleware('can:reports.clinical')
            ->name('surgeries');

        Route::get('/expiry', ExpiryReportController::class)
            ->middleware('can:inventory.view')
            ->name('expiry');
    });
});
