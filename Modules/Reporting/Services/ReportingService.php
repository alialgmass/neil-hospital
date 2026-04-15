<?php

namespace Modules\Reporting\Services;

use Illuminate\Support\Facades\DB;

class ReportingService
{
    // 1. Department Revenue Report
    public function deptRevenue(string $from, string $to): array
    {
        $rows = DB::table('bookings')
            ->leftJoin('doctors', 'bookings.doctor_id', '=', 'doctors.id')
            ->select(
                'bookings.dept',
                'doctors.name as doctor_name',
                DB::raw('COUNT(*) as cases'),
                DB::raw('SUM(bookings.price) as revenue'),
                DB::raw('SUM(bookings.ins_amount) as ins_amount'),
                DB::raw('SUM(bookings.price - bookings.ins_amount) as patient_amount'),
            )
            ->where('bookings.pay_status', '!=', 'unpaid')
            ->whereBetween('bookings.visit_date', [$from, $to])
            ->groupBy('bookings.dept', 'doctors.id', 'doctors.name')
            ->orderBy('bookings.dept')
            ->orderByDesc('revenue')
            ->get()
            ->toArray();

        $total = array_sum(array_column($rows, 'revenue'));

        return compact('rows', 'total', 'from', 'to');
    }

    // 2. Cases Report
    public function cases(string $from, string $to, ?string $dept = null): array
    {
        $rows = DB::table('bookings')
            ->leftJoin('doctors', 'bookings.doctor_id', '=', 'doctors.id')
            ->select(
                'bookings.file_no',
                'bookings.patient_name',
                'bookings.dept',
                'bookings.service_name',
                'doctors.name as doctor_name',
                'bookings.price',
                'bookings.pay_status',
                'bookings.status',
                'bookings.visit_date',
            )
            ->when($dept, fn ($q, $v) => $q->where('bookings.dept', $v))
            ->whereBetween('bookings.visit_date', [$from, $to])
            ->orderByDesc('bookings.visit_date')
            ->get()
            ->toArray();

        return compact('rows', 'from', 'to', 'dept');
    }

    // 3. Doctor Claims Report
    public function doctorClaims(string $from, string $to, ?string $doctorId = null): array
    {
        $rows = DB::table('bookings')
            ->join('doctors', 'bookings.doctor_id', '=', 'doctors.id')
            ->select(
                'doctors.id as doctor_id',
                'doctors.name as doctor_name',
                'doctors.fee_type',
                'doctors.fee_value',
                DB::raw('COUNT(*) as cases'),
                DB::raw('SUM(bookings.price) as total_billed'),
                DB::raw('SUM(bookings.ins_amount) as ins_amount'),
            )
            ->where('bookings.pay_status', '!=', 'unpaid')
            ->whereBetween('bookings.visit_date', [$from, $to])
            ->when($doctorId, fn ($q, $v) => $q->where('bookings.doctor_id', $v))
            ->groupBy('doctors.id', 'doctors.name', 'doctors.fee_type', 'doctors.fee_value')
            ->orderBy('doctors.name')
            ->get()
            ->map(function ($row) {
                $billed = (float) $row->total_billed;
                $ins = (float) $row->ins_amount;
                $net = $billed - $ins;
                $feeValue = (float) $row->fee_value;

                $doctorClaim = match ($row->fee_type) {
                    'percentage' => round($net * $feeValue / 100, 2),
                    'fixed' => $row->cases * $feeValue,
                    default => 0,
                };

                return (object) [
                    ...(array) $row,
                    'net_billed' => $net,
                    'doctor_claim' => $doctorClaim,
                    'center_share' => round($net - $doctorClaim, 2),
                ];
            })
            ->toArray();

        return compact('rows', 'from', 'to');
    }

    // 4. Doctor Payments Report
    public function doctorPayments(string $from, string $to, ?string $doctorId = null): array
    {
        $rows = DB::table('dr_payments')
            ->join('doctors', 'dr_payments.doctor_id', '=', 'doctors.id')
            ->leftJoin('users', 'dr_payments.created_by', '=', 'users.id')
            ->select(
                'doctors.name as doctor_name',
                'dr_payments.amount',
                'dr_payments.period_from',
                'dr_payments.period_to',
                'dr_payments.paid_at',
                'users.name as paid_by_name',
                'dr_payments.notes',
            )
            ->when($doctorId, fn ($q, $v) => $q->where('dr_payments.doctor_id', $v))
            ->whereBetween('dr_payments.paid_at', [$from, $to])
            ->orderByDesc('dr_payments.paid_at')
            ->get()
            ->toArray();

        $total = array_sum(array_column($rows, 'amount'));

        return compact('rows', 'total', 'from', 'to');
    }

    // 5. Insurance Claims Report
    public function insuranceClaims(string $from, string $to, ?string $companyId = null): array
    {
        $rows = DB::table('bookings')
            ->leftJoin('insurance_companies', 'bookings.ins_company_id', '=', 'insurance_companies.id')
            ->select(
                'insurance_companies.name as company_name',
                DB::raw('COUNT(*) as cases'),
                DB::raw('SUM(bookings.price) as total_billed'),
                DB::raw('SUM(bookings.ins_amount) as ins_amount'),
                DB::raw('SUM(bookings.price - bookings.ins_amount) as patient_amount'),
            )
            ->where('bookings.pay_method', 'insurance')
            ->whereBetween('bookings.visit_date', [$from, $to])
            ->when($companyId, fn ($q, $v) => $q->where('bookings.ins_company_id', $v))
            ->groupBy('insurance_companies.id', 'insurance_companies.name')
            ->orderByDesc('ins_amount')
            ->get()
            ->toArray();

        return compact('rows', 'from', 'to');
    }

    // 6. Inventory Movement Report
    public function inventoryMovement(string $from, string $to, ?string $itemId = null): array
    {
        $rows = DB::table('stock_permit_items')
            ->join('stock_permits', 'stock_permit_items.permit_id', '=', 'stock_permits.id')
            ->leftJoin('inventory', 'stock_permit_items.item_id', '=', 'inventory.id')
            ->select(
                'inventory.name as item_name',
                'inventory.unit',
                'stock_permits.type',
                'stock_permits.permit_no',
                'stock_permits.department',
                'stock_permit_items.qty',
                'stock_permit_items.unit_cost',
                DB::raw('stock_permit_items.qty * stock_permit_items.unit_cost as total'),
                'stock_permits.created_at as permit_date',
            )
            ->when($itemId, fn ($q, $v) => $q->where('stock_permit_items.item_id', $v))
            ->whereBetween('stock_permits.created_at', [$from, $to.' 23:59:59'])
            ->orderByDesc('stock_permits.created_at')
            ->get()
            ->toArray();

        return compact('rows', 'from', 'to');
    }

    // 7. Purchase Prices Report
    public function purchasePrices(string $from, string $to): array
    {
        $rows = DB::table('purchase_invoice_items')
            ->join('purchase_invoices', 'purchase_invoice_items.invoice_id', '=', 'purchase_invoices.id')
            ->leftJoin('inventory', 'purchase_invoice_items.item_id', '=', 'inventory.id')
            ->leftJoin('suppliers', 'purchase_invoices.supplier_id', '=', 'suppliers.id')
            ->select(
                'purchase_invoice_items.item_name',
                'suppliers.name as supplier_name',
                DB::raw('AVG(purchase_invoice_items.unit_cost) as avg_cost'),
                DB::raw('MIN(purchase_invoice_items.unit_cost) as min_cost'),
                DB::raw('MAX(purchase_invoice_items.unit_cost) as max_cost'),
                DB::raw('SUM(purchase_invoice_items.qty) as total_qty'),
                DB::raw('SUM(purchase_invoice_items.total) as total_value'),
            )
            ->whereBetween('purchase_invoices.invoice_date', [$from, $to])
            ->groupBy('purchase_invoice_items.item_id', 'purchase_invoice_items.item_name', 'suppliers.id', 'suppliers.name')
            ->orderBy('purchase_invoice_items.item_name')
            ->get()
            ->toArray();

        return compact('rows', 'from', 'to');
    }

    // 8. Profit & Loss Report
    public function profitLoss(string $from, string $to): array
    {
        $revenues = DB::table('journal_entries')
            ->join('accounts', 'journal_entries.credit_account_id', '=', 'accounts.id')
            ->where('accounts.group', 'revenues')
            ->whereBetween('journal_entries.date', [$from, $to])
            ->select('accounts.name', DB::raw('SUM(journal_entries.amount) as amount'))
            ->groupBy('accounts.id', 'accounts.name')
            ->get()
            ->toArray();

        $expenses = DB::table('journal_entries')
            ->join('accounts', 'journal_entries.debit_account_id', '=', 'accounts.id')
            ->where('accounts.group', 'expenses')
            ->whereBetween('journal_entries.date', [$from, $to])
            ->select('accounts.name', DB::raw('SUM(journal_entries.amount) as amount'))
            ->groupBy('accounts.id', 'accounts.name')
            ->get()
            ->toArray();

        $totalRevenue = array_sum(array_column($revenues, 'amount'));
        $totalExpense = array_sum(array_column($expenses, 'amount'));
        $netIncome = $totalRevenue - $totalExpense;

        return compact('revenues', 'expenses', 'totalRevenue', 'totalExpense', 'netIncome', 'from', 'to');
    }

    // 9. Expense Analysis Report
    public function expenseAnalysis(string $from, string $to): array
    {
        $rows = DB::table('journal_entries')
            ->join('accounts', 'journal_entries.debit_account_id', '=', 'accounts.id')
            ->where('accounts.group', 'expenses')
            ->whereBetween('journal_entries.date', [$from, $to])
            ->select(
                'accounts.code',
                'accounts.name',
                DB::raw('COUNT(*) as entries'),
                DB::raw('SUM(journal_entries.amount) as total'),
            )
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name')
            ->orderByDesc('total')
            ->get()
            ->toArray();

        $total = array_sum(array_column($rows, 'total'));

        return compact('rows', 'total', 'from', 'to');
    }

    // 10. System Log Report
    public function systemLog(string $from, string $to, ?string $module = null): array
    {
        $rows = DB::table('activity_logs')
            ->leftJoin('users', 'activity_logs.user_id', '=', 'users.id')
            ->select(
                'users.name as user_name',
                'activity_logs.action',
                'activity_logs.module',
                'activity_logs.description',
                'activity_logs.ip_address',
                'activity_logs.created_at',
            )
            ->when($module, fn ($q, $v) => $q->where('activity_logs.module', $v))
            ->whereBetween('activity_logs.created_at', [$from, $to.' 23:59:59'])
            ->orderByDesc('activity_logs.created_at')
            ->limit(500)
            ->get()
            ->toArray();

        return compact('rows', 'from', 'to');
    }
}
