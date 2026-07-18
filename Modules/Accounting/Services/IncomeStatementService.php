<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\AccountGroup;
use Modules\Accounting\Models\Account;

class IncomeStatementService
{
    private const TAX_RATE = 0.15;

    /**
     * Expense codes by category, per the Al-Nour accounting guide.
     */
    private const COST_OF_SERVICES = ['5010', '5020', '5030'];

    private const DOCTOR_FEES = ['5110', '5120', '5130'];

    /**
     * Build a detailed income statement grouped by the four sections from the guide:
     * (1) Revenues, (2) Cost of Services, (3) Doctor Fees, (4) Operating Expenses.
     */
    public function get(?string $from = null, ?string $to = null): array
    {
        $accounts = Account::where('is_active', true)
            ->whereIn('group', [AccountGroup::Revenues->value, AccountGroup::Expenses->value])
            ->orderBy('code')
            ->get();

        $revenues = [];
        $costOfServices = [];
        $doctorFees = [];
        $operatingExpenses = [];

        $totalRevenue = 0.0;
        $totalCost = 0.0;
        $totalDoctorFees = 0.0;
        $totalOperating = 0.0;

        foreach ($accounts as $account) {
            $debits = (float) DB::table('journal_entries')
                ->where('debit_account_id', $account->id)
                ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
                ->sum('amount');

            $credits = (float) DB::table('journal_entries')
                ->where('credit_account_id', $account->id)
                ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
                ->sum('amount');

            $balance = $account->group === AccountGroup::Revenues
                ? $credits - $debits
                : $debits - $credits;

            // Skip parent/summary accounts (no direct movements, balance = 0)
            if ($balance == 0.0 && $debits == 0.0 && $credits == 0.0) {
                continue;
            }

            $row = ['code' => $account->code, 'name' => $account->name, 'balance' => $balance];

            if ($account->group === AccountGroup::Revenues) {
                $revenues[] = $row;
                $totalRevenue += $balance;
            } elseif (in_array($account->code, self::COST_OF_SERVICES)) {
                $costOfServices[] = $row;
                $totalCost += $balance;
            } elseif (in_array($account->code, self::DOCTOR_FEES)) {
                $doctorFees[] = $row;
                $totalDoctorFees += $balance;
            } else {
                $operatingExpenses[] = $row;
                $totalOperating += $balance;
            }
        }

        $grossProfit = $totalRevenue - $totalCost;
        $netBeforeTax = $grossProfit - $totalDoctorFees - $totalOperating;
        $tax = max(0, round($netBeforeTax * self::TAX_RATE, 2));
        $netIncome = $netBeforeTax - $tax;

        return [
            'revenues' => $revenues,
            'costOfServices' => $costOfServices,
            'doctorFees' => $doctorFees,
            'operatingExpenses' => $operatingExpenses,
            'totalRevenue' => $totalRevenue,
            'totalCost' => $totalCost,
            'grossProfit' => $grossProfit,
            'totalDoctorFees' => $totalDoctorFees,
            'totalOperating' => $totalOperating,
            'netBeforeTax' => $netBeforeTax,
            'tax' => $tax,
            'netIncome' => $netIncome,
            // Keep for backward compatibility with any existing callers
            'expenses' => array_merge($costOfServices, $doctorFees, $operatingExpenses),
            'totalExpense' => $totalCost + $totalDoctorFees + $totalOperating,
        ];
    }
}
