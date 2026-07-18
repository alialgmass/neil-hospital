<?php

namespace Modules\Accounting\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\AccountGroup;
use Modules\Accounting\Models\MonthlySettlement;
use Modules\Doctor\Models\DoctorPayment;

class MonthlySettlementService
{
    /**
     * Preview the settlement data for a specific month.
     */
    public function preview(string $monthPeriod): array
    {
        $startOfMonth = Carbon::parse($monthPeriod.'-01')->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        // 1. Calculate Revenue (Credits to Revenue accounts)
        $revenue = DB::table('journal_entries')
            ->join('accounts', 'journal_entries.credit_account_id', '=', 'accounts.id')
            ->where('accounts.group', AccountGroup::Revenues->value)
            ->whereBetween('journal_entries.date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('journal_entries.amount');

        // 2. Calculate Expenses (Debits to Expense accounts)
        $expenses = DB::table('journal_entries')
            ->join('accounts', 'journal_entries.debit_account_id', '=', 'accounts.id')
            ->where('accounts.group', AccountGroup::Expenses->value)
            ->whereBetween('journal_entries.date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('journal_entries.amount');

        // 3. Calculate Doctor Claims (Payments recorded in this period)
        $drClaims = DoctorPayment::whereBetween('paid_at', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('amount');

        $net = (float) $revenue - (float) $expenses;

        return [
            'month' => $monthPeriod,
            'total_revenue' => (float) $revenue,
            'total_expenses' => (float) $expenses,
            'total_doctor_claims' => (float) $drClaims,
            'net_surplus_deficit' => $net,
            'is_locked' => $this->isLocked($monthPeriod),
        ];
    }

    /**
     * Lock the period and save the settlement record.
     */
    public function lock(string $monthPeriod, ?string $notes = null): MonthlySettlement
    {
        $data = $this->preview($monthPeriod);

        return MonthlySettlement::updateOrCreate(
            ['month_period' => $monthPeriod],
            [
                'total_revenue' => $data['total_revenue'],
                'total_expenses' => $data['total_expenses'],
                'total_doctor_claims' => $data['total_doctor_claims'],
                'net_surplus_deficit' => $data['net_surplus_deficit'],
                'is_locked' => true,
                'locked_at' => now(),
                'locked_by' => auth()->id(),
                'notes' => $notes,
            ]
        );
    }

    /**
     * Check if a month is locked.
     */
    public function isLocked(string $monthPeriod): bool
    {
        return MonthlySettlement::where('month_period', $monthPeriod)
            ->where('is_locked', true)
            ->exists();
    }

    /**
     * Check if a specific date falls within a locked period.
     */
    public function isDateLocked(string $date): bool
    {
        $period = Carbon::parse($date)->format('Y-m');

        return $this->isLocked($period);
    }
}
