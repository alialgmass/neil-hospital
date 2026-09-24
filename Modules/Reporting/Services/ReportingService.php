<?php

namespace Modules\Reporting\Services;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\CostCenter;
use Modules\Admin\Enums\SystemModule;
use Modules\Doctor\Enums\DelegationStatus;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Services\DoctorClaimsService;
use Modules\Insurance\States\ClaimStatus;

class ReportingService
{
    public function __construct(private readonly DoctorClaimsService $doctorClaimsService) {}

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
            ->whereIn('bookings.dept', SystemModule::enabledDeptValues())
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
            ->whereIn('bookings.dept', SystemModule::enabledDeptValues())
            ->whereBetween('bookings.visit_date', [$from, $to])
            ->orderByDesc('bookings.visit_date')
            ->get()
            ->toArray();

        return compact('rows', 'from', 'to', 'dept');
    }

    // 3. Doctor Claims Report
    public function doctorClaims(string $from, string $to, ?string $doctorId = null): array
    {
        $bookings = DB::table('bookings')
            ->whereNotNull('doctor_id')
            ->where('pay_status', '!=', 'unpaid')
            ->whereBetween('visit_date', [$from, $to])
            ->whereIn('dept', SystemModule::enabledDeptValues())
            ->when($doctorId, fn ($q, $v) => $q->where('doctor_id', $v))
            ->get();

        $doctors = Doctor::whereIn('id', $bookings->pluck('doctor_id')->unique())->get()->keyBy('id');

        // Per (doctor, booking) debt already settled out of that doctor's
        // share (see Doctor::settleDebtForBooking()) — netted out below so a
        // booking whose share was diverted to pay down old debt never keeps
        // showing as still "مستحق" (see DoctorDebtSettlement).
        $debtSettled = DB::table('doctor_debt_settlements')
            ->when($doctorId, fn ($q, $v) => $q->where('doctor_id', $v))
            ->select('doctor_id', 'booking_id', DB::raw('SUM(amount) as total'))
            ->groupBy('doctor_id', 'booking_id')
            ->get()
            ->keyBy(fn ($row) => "{$row->doctor_id}:{$row->booking_id}");

        $rows = $bookings->groupBy('doctor_id')
            ->map(function ($doctorBookings, $doctorId) use ($doctors, $debtSettled) {
                $doctor = $doctors->get($doctorId);
                if (! $doctor) {
                    return null;
                }

                $totalBilled = (float) $doctorBookings->sum('price');
                $insAmount = (float) $doctorBookings->sum('ins_amount');
                $netBilled = $totalBilled - $insAmount;

                // computeDrShare() already nets out any delegated/anesthesia
                // amount for this booking (see DoctorClaimsService::delegatedTotal()).
                $doctorClaim = (float) $doctorBookings->sum(function ($booking) use ($doctor, $debtSettled) {
                    $share = $this->doctorClaimsService->computeDrShare($doctor, $booking);
                    $settled = (float) ($debtSettled->get("{$doctor->id}:{$booking->id}")->total ?? 0);

                    return max(0.0, round($share - $settled, 2));
                });

                return (object) [
                    'doctor_id' => $doctor->id,
                    'doctor_name' => $doctor->name,
                    'fee_type' => $doctor->fee_type->value,
                    'cases' => $doctorBookings->count(),
                    'total_billed' => $totalBilled,
                    'ins_amount' => $insAmount,
                    'net_billed' => $netBilled,
                    'doctor_claim' => round($doctorClaim, 2),
                    'center_share' => round($netBilled - $doctorClaim, 2),
                    'last_visit' => $doctorBookings->max('visit_date'),
                ];
            })
            ->filter()
            ->keyBy('doctor_id');

        // Delegated/anesthesia dues: earned by a doctor who isn't
        // bookings.doctor_id, so they never appear in the groupBy above.
        // Merged into the same doctor's row when they also have primary
        // bookings in the period, otherwise added as a claim-only row.
        $delegationRows = DB::table('booking_doctor_delegations')
            ->join('bookings', 'bookings.id', '=', 'booking_doctor_delegations.booking_id')
            ->where('booking_doctor_delegations.status', '!=', DelegationStatus::Void->value)
            ->whereBetween('bookings.visit_date', [$from, $to])
            ->whereIn('bookings.dept', SystemModule::enabledDeptValues())
            ->when($doctorId, fn ($q, $v) => $q->where('booking_doctor_delegations.doctor_id', $v))
            ->select(
                'booking_doctor_delegations.doctor_id',
                'booking_doctor_delegations.booking_id',
                'booking_doctor_delegations.amount',
                'bookings.visit_date',
            )
            ->get()
            ->groupBy('doctor_id');

        foreach ($delegationRows as $delegateDoctorId => $lines) {
            $doctor = Doctor::find($delegateDoctorId);

            if (! $doctor) {
                continue;
            }

            $netAmount = (float) $lines->sum(function ($line) use ($delegateDoctorId, $debtSettled) {
                $settled = (float) ($debtSettled->get("{$delegateDoctorId}:{$line->booking_id}")->total ?? 0);

                return max(0.0, round((float) $line->amount - $settled, 2));
            });
            $cases = $lines->pluck('booking_id')->unique()->count();
            $lastVisit = $lines->max('visit_date');

            $existing = $rows->get($delegateDoctorId);

            if ($existing) {
                $existing->cases += $cases;
                $existing->doctor_claim = round($existing->doctor_claim + $netAmount, 2);
                $existing->last_visit = max($existing->last_visit, $lastVisit);

                continue;
            }

            $rows->put($delegateDoctorId, (object) [
                'doctor_id' => $doctor->id,
                'doctor_name' => $doctor->name,
                'fee_type' => $doctor->fee_type->value,
                'cases' => $cases,
                'total_billed' => 0.0,
                'ins_amount' => 0.0,
                'net_billed' => 0.0,
                'doctor_claim' => round($netAmount, 2),
                'center_share' => 0.0,
                'last_visit' => $lastVisit,
            ]);
        }

        $rows = $rows->sortByDesc('last_visit')->values()->toArray();

        return compact('rows', 'from', 'to');
    }

    // 4. Doctor Payments Report
    public function doctorPayments(string $from, string $to, ?string $doctorId = null): array
    {
        $rows = DB::table('dr_payments')
            ->join('doctors', 'dr_payments.doctor_id', '=', 'doctors.id')
            ->leftJoin('users', 'dr_payments.created_by', '=', 'users.id')
            ->select(
                'doctors.id as doctor_id',
                'doctors.name as doctor_name',
                'dr_payments.amount',
                'dr_payments.method',
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
            ->whereIn('bookings.dept', SystemModule::enabledDeptValues())
            ->whereBetween('bookings.visit_date', [$from, $to])
            ->when($companyId, fn ($q, $v) => $q->where('bookings.ins_company_id', $v))
            ->groupBy('insurance_companies.id', 'insurance_companies.name')
            ->orderByDesc('ins_amount')
            ->get()
            ->toArray();

        $statusCounts = DB::table('insurance_claims')
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(insurance_share) as total'))
            ->whereBetween('claim_date', [$from, $to])
            ->when($companyId, fn ($q, $v) => $q->where('insurance_company_id', $v))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $statusBreakdown = collect(ClaimStatus::labels())
            ->map(fn (string $label, string $status) => [
                'status' => $status,
                'label' => $label,
                'count' => (int) ($statusCounts->get($status)->count ?? 0),
                'total' => (float) ($statusCounts->get($status)->total ?? 0),
            ])
            ->values()
            ->toArray();

        return compact('rows', 'statusBreakdown', 'from', 'to');
    }

    // 6. Inventory Movement Report
    public function inventoryMovement(string $from, string $to, ?string $itemId = null): array
    {
        // Manual stock-in/stock-out/adjustment permits.
        $permitMovements = DB::table('stock_permit_items')
            ->join('stock_permits', 'stock_permit_items.permit_id', '=', 'stock_permits.id')
            ->leftJoin('inventory', 'stock_permit_items.item_id', '=', 'inventory.id')
            ->select(
                'inventory.name as item_name',
                'inventory.unit',
                'stock_permits.type',
                'stock_permits.permit_no as reference_no',
                'stock_permits.department as party',
                'stock_permit_items.qty',
                'stock_permit_items.unit_cost',
                DB::raw('stock_permit_items.qty * stock_permit_items.unit_cost as total'),
                'stock_permits.created_at as movement_date',
            )
            ->when($itemId, fn ($q, $v) => $q->where('stock_permit_items.item_id', $v))
            ->whereBetween('stock_permits.created_at', [$from, $to.' 23:59:59']);

        // Purchase invoice receipts also increase stock (see PurchaseInvoiceService::create())
        // but never create a stock_permit row, so they were previously missing entirely
        // from this report's "in" movements.
        $purchaseMovements = DB::table('purchase_invoice_items')
            ->join('purchase_invoices', 'purchase_invoice_items.invoice_id', '=', 'purchase_invoices.id')
            ->leftJoin('inventory', 'purchase_invoice_items.item_id', '=', 'inventory.id')
            ->leftJoin('suppliers', 'purchase_invoices.supplier_id', '=', 'suppliers.id')
            ->select(
                'inventory.name as item_name',
                'inventory.unit',
                DB::raw("'in' as type"),
                'purchase_invoices.invoice_no as reference_no',
                'suppliers.name as party',
                'purchase_invoice_items.qty',
                'purchase_invoice_items.unit_cost',
                'purchase_invoice_items.total',
                'purchase_invoices.invoice_date as movement_date',
            )
            ->when($itemId, fn ($q, $v) => $q->where('purchase_invoice_items.item_id', $v))
            ->whereBetween('purchase_invoices.invoice_date', [$from, $to]);

        $rows = $permitMovements->unionAll($purchaseMovements)
            ->orderByDesc('movement_date')
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

    /**
     * Revenue, expenses and net profit per cost center for a date range
     * (a month, a year, or an arbitrary range — the caller picks $from/$to).
     * Derived entirely from account `group` (revenues/expenses) and the
     * `cost_center` tag already carried by every journal entry — no
     * hardcoded account-code list.
     *
     * Revenue/expense are NET of reversals: a reversal swaps debit/credit
     * accounts of the original entry, so a revenue account can appear on
     * either side — summing only "credits to revenue accounts" would leave
     * a rejected/reversed claim's revenue overstated. Netting credit-side
     * against debit-side (and vice versa for expenses) cancels it out
     * correctly.
     */
    public function costCenterProfitability(string $from, string $to): array
    {
        $netByCenter = function (string $group, string $creditJoinColumn, string $debitJoinColumn) use ($from, $to) {
            $credits = DB::table('journal_entries')
                ->join('accounts', "journal_entries.{$creditJoinColumn}", '=', 'accounts.id')
                ->where('accounts.group', $group)
                ->whereBetween('journal_entries.date', [$from, $to])
                ->whereNotNull('journal_entries.cost_center')
                ->select('journal_entries.cost_center', DB::raw('SUM(journal_entries.amount) as amount'))
                ->groupBy('journal_entries.cost_center')
                ->pluck('amount', 'cost_center');

            $debits = DB::table('journal_entries')
                ->join('accounts', "journal_entries.{$debitJoinColumn}", '=', 'accounts.id')
                ->where('accounts.group', $group)
                ->whereBetween('journal_entries.date', [$from, $to])
                ->whereNotNull('journal_entries.cost_center')
                ->select('journal_entries.cost_center', DB::raw('SUM(journal_entries.amount) as amount'))
                ->groupBy('journal_entries.cost_center')
                ->pluck('amount', 'cost_center');

            return $credits->keys()->merge($debits->keys())->unique()
                ->mapWithKeys(fn ($center) => [$center => (float) ($credits[$center] ?? 0) - (float) ($debits[$center] ?? 0)]);
        };

        // Revenue accounts are credit-nature: net = credits − debits (a
        // reversal debits the revenue account, reducing it back out).
        $revenueByCenter = $netByCenter('revenues', 'credit_account_id', 'debit_account_id');
        // Expense accounts are debit-nature: net = debits − credits.
        $expenseByCenter = $netByCenter('expenses', 'debit_account_id', 'credit_account_id');

        $centers = $revenueByCenter->keys()->merge($expenseByCenter->keys())->unique()->sort()->values();

        $rows = $centers->map(function (string $center) use ($revenueByCenter, $expenseByCenter) {
            $revenue = round($revenueByCenter[$center] ?? 0.0, 2);
            $expense = round($expenseByCenter[$center] ?? 0.0, 2);

            return [
                'cost_center' => $center,
                'label' => CostCenter::tryFrom($center)?->label() ?? $center,
                'revenue' => $revenue,
                'expense' => $expense,
                'profit' => round($revenue - $expense, 2),
            ];
        })->values()->toArray();

        $totalRevenue = round(array_sum(array_column($rows, 'revenue')), 2);
        $totalExpense = round(array_sum(array_column($rows, 'expense')), 2);
        $netProfit = round($totalRevenue - $totalExpense, 2);

        return compact('rows', 'totalRevenue', 'totalExpense', 'netProfit', 'from', 'to');
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

    // 10. HR Attendance Report
    public function hrAttendance(string $from, string $to, ?string $employeeId = null): array
    {
        $rows = DB::table('attendances')
            ->join('employees', 'attendances.employee_id', '=', 'employees.id')
            ->leftJoin('shifts', 'attendances.shift_id', '=', 'shifts.id')
            ->select(
                'employees.name as employee_name',
                'employees.employee_no',
                'employees.dept',
                'attendances.date',
                'attendances.status',
                'attendances.check_in',
                'attendances.check_out',
                'attendances.overtime_hours',
                'shifts.name as shift_name',
            )
            ->when($employeeId, fn ($q, $v) => $q->where('attendances.employee_id', $v))
            ->whereBetween('attendances.date', [$from, $to])
            ->orderByDesc('attendances.date')
            ->orderBy('employees.name')
            ->get()
            ->toArray();

        $summary = [
            'present' => collect($rows)->where('status', 'present')->count(),
            'absent' => collect($rows)->where('status', 'absent')->count(),
            'late' => collect($rows)->where('status', 'late')->count(),
            'half_day' => collect($rows)->where('status', 'half_day')->count(),
            'on_leave' => collect($rows)->where('status', 'on_leave')->count(),
            'overtime_hours' => (float) collect($rows)->sum('overtime_hours'),
        ];

        return compact('rows', 'summary', 'from', 'to');
    }

    // 11. HR Payroll Report
    public function hrPayroll(int $month, int $year): array
    {
        $rows = DB::table('payrolls')
            ->join('employees', 'payrolls.employee_id', '=', 'employees.id')
            ->select(
                'employees.name as employee_name',
                'employees.employee_no',
                'employees.dept',
                'employees.position',
                'payrolls.month',
                'payrolls.year',
                'payrolls.base_salary',
                'payrolls.allowances',
                'payrolls.overtime_pay',
                'payrolls.deductions',
                'payrolls.net_salary',
                'payrolls.status',
                'payrolls.paid_at',
            )
            ->where('payrolls.month', $month)
            ->where('payrolls.year', $year)
            ->orderBy('employees.dept')
            ->orderBy('employees.name')
            ->get()
            ->toArray();

        $totals = [
            'base_salary' => array_sum(array_column($rows, 'base_salary')),
            'allowances' => array_sum(array_column($rows, 'allowances')),
            'overtime_pay' => array_sum(array_column($rows, 'overtime_pay')),
            'deductions' => array_sum(array_column($rows, 'deductions')),
            'net_salary' => array_sum(array_column($rows, 'net_salary')),
            'paid_count' => collect($rows)->where('status', 'paid')->count(),
            'draft_count' => collect($rows)->where('status', 'draft')->count(),
        ];

        return compact('rows', 'totals', 'month', 'year');
    }

    // 12. HR Leaves Report
    public function hrLeaves(string $from, string $to, ?string $employeeId = null, ?string $type = null): array
    {
        $rows = DB::table('hr_leaves')
            ->join('employees', 'hr_leaves.employee_id', '=', 'employees.id')
            ->select(
                'employees.name as employee_name',
                'employees.employee_no',
                'employees.dept',
                'hr_leaves.type',
                'hr_leaves.from_date',
                'hr_leaves.to_date',
                'hr_leaves.days',
                'hr_leaves.status',
                'hr_leaves.reason',
            )
            ->when($employeeId, fn ($q, $v) => $q->where('hr_leaves.employee_id', $v))
            ->when($type, fn ($q, $v) => $q->where('hr_leaves.type', $v))
            ->whereBetween('hr_leaves.from_date', [$from, $to])
            ->orderByDesc('hr_leaves.from_date')
            ->get()
            ->toArray();

        $summary = [
            'total_days' => (int) collect($rows)->sum('days'),
            'approved' => collect($rows)->where('status', 'approved')->count(),
            'pending' => collect($rows)->where('status', 'pending')->count(),
            'rejected' => collect($rows)->where('status', 'rejected')->count(),
        ];

        return compact('rows', 'summary', 'from', 'to');
    }

    // 13. System Log Report
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
