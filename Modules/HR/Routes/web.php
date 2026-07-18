<?php

use Illuminate\Support\Facades\Route;
use Modules\HR\Controllers\AttendanceController;
use Modules\HR\Controllers\EmployeeController;
use Modules\HR\Controllers\LeaveController;
use Modules\HR\Controllers\PayrollController;
use Modules\HR\Controllers\ShiftController;
use Modules\HR\Controllers\ShiftHandoverController;

Route::middleware(['auth', 'verified'])->group(function () {
    // Employees
    Route::get('/employees', [EmployeeController::class, 'index'])->middleware('can:hr.view')->name('employees.index');
    Route::post('/employees', [EmployeeController::class, 'store'])->middleware('can:hr.manage')->name('employees.store');
    Route::put('/employees/{id}', [EmployeeController::class, 'update'])->middleware('can:hr.manage')->name('employees.update');

    // Shifts
    Route::get('/shifts', [ShiftController::class, 'index'])->middleware('can:hr.view')->name('shifts.index');
    Route::post('/shifts', [ShiftController::class, 'store'])->middleware('can:hr.manage')->name('shifts.store');
    Route::put('/shifts/{id}', [ShiftController::class, 'update'])->middleware('can:hr.manage')->name('shifts.update');

    // Attendance
    Route::get('/attendance', [AttendanceController::class, 'index'])->middleware('can:hr.view')->name('attendance.index');
    Route::post('/attendance', [AttendanceController::class, 'store'])->middleware('can:hr.manage')->name('attendance.store');

    // Shift Handovers
    Route::get('/shift-handovers', [ShiftHandoverController::class, 'index'])->middleware('can:hr.view')->name('shift-handovers.index');
    Route::post('/shift-handovers', [ShiftHandoverController::class, 'store'])->middleware('can:hr.manage')->name('shift-handovers.store');
    Route::post('/shift-handovers/{id}/accept', [ShiftHandoverController::class, 'accept'])->middleware('can:hr.manage')->name('shift-handovers.accept');

    // Leaves
    Route::get('/leaves', [LeaveController::class, 'index'])->middleware('can:hr.view')->name('leaves.index');
    Route::post('/leaves', [LeaveController::class, 'store'])->middleware('can:hr.manage')->name('leaves.store');
    Route::post('/leaves/{id}/approve', [LeaveController::class, 'approve'])->middleware('can:hr.manage')->name('leaves.approve');
    Route::post('/leaves/{id}/reject', [LeaveController::class, 'reject'])->middleware('can:hr.manage')->name('leaves.reject');

    // Payroll
    Route::get('/payroll', [PayrollController::class, 'index'])->middleware('can:hr.manage')->name('payroll.index');
    Route::post('/payroll/generate', [PayrollController::class, 'generate'])->middleware('can:hr.manage')->name('payroll.generate');
    Route::put('/payroll/{id}', [PayrollController::class, 'update'])->middleware('can:hr.manage')->name('payroll.update');
    Route::post('/payroll/{id}/approve', [PayrollController::class, 'approve'])->middleware('can:hr.manage')->name('payroll.approve');
    Route::post('/payroll/{id}/pay', [PayrollController::class, 'markPaid'])->middleware('can:hr.manage')->name('payroll.pay');
    Route::post('/payroll/{id}/recalculate', [PayrollController::class, 'recalculate'])->middleware('can:hr.manage')->name('payroll.recalculate');
});
