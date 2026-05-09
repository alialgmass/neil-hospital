<?php

namespace Modules\HR\Services;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Admin\Services\SettingsService;
use Modules\HR\Enums\AttendanceStatus;
use Modules\HR\Enums\EmployeeStatus;
use Modules\HR\Enums\HandoverStatus;
use Modules\HR\Enums\LeaveStatus;
use Modules\HR\Enums\PayrollStatus;
use Modules\HR\Models\Attendance;
use Modules\HR\Models\Employee;
use Modules\HR\Models\Leave;
use Modules\HR\Models\Payroll;
use Modules\HR\Models\Shift;
use Modules\HR\Models\ShiftHandover;

class HRService
{
    public function __construct(private readonly SettingsService $settings) {}
    // ── Employees ──────────────────────────────────────────────────────────

    public function listEmployees(array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        return Employee::query()
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where('name', 'like', "%{$v}%")->orWhere('employee_no', 'like', "%{$v}%"))
            ->when($filters['dept'] ?? null, fn ($q, $v) => $q->where('dept', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function getActiveEmployees(): Collection
    {
        return Employee::where('status', EmployeeStatus::Active)
            ->orderBy('name')
            ->get(['id', 'name', 'dept', 'position', 'base_salary', 'allowances']);
    }

    public function getDepts(): Collection
    {
        return Employee::query()->whereNotNull('dept')->distinct()->orderBy('dept')->pluck('dept');
    }

    public function nextEmployeeNo(): string
    {
        $last = Employee::max('employee_no');
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            return 'EMP-'.str_pad((int) $m[1] + 1, 4, '0', STR_PAD_LEFT);
        }

        return 'EMP-0001';
    }

    public function createEmployee(array $data): Employee
    {
        return Employee::create($data);
    }

    public function updateEmployee(string $id, array $data): Employee
    {
        $employee = Employee::findOrFail($id);
        $employee->update($data);

        return $employee;
    }

    // ── Shifts ─────────────────────────────────────────────────────────────

    public function listShifts(): Collection
    {
        return Shift::orderBy('start_time')->get();
    }

    public function createShift(array $data): Shift
    {
        return Shift::create($data);
    }

    public function updateShift(string $id, array $data): Shift
    {
        $shift = Shift::findOrFail($id);
        $shift->update($data);

        return $shift;
    }

    // ── Attendance ─────────────────────────────────────────────────────────

    public function getDailyAttendance(string $date): Collection
    {
        $employees = Employee::where('status', EmployeeStatus::Active)
            ->orderBy('name')
            ->get(['id', 'name', 'dept', 'position']);

        $records = Attendance::where('date', $date)->get()->keyBy('employee_id');

        return $employees->map(fn ($emp) => [
            'employee_id' => $emp->id,
            'employee_name' => $emp->name,
            'dept' => $emp->dept,
            'position' => $emp->position,
            'status' => $records->get($emp->id)?->status?->value ?? '',
            'check_in' => $records->get($emp->id)?->check_in ?? '',
            'check_out' => $records->get($emp->id)?->check_out ?? '',
            'overtime_hours' => $records->get($emp->id)?->overtime_hours ?? 0,
            'notes' => $records->get($emp->id)?->notes ?? '',
            'shift_id' => $records->get($emp->id)?->shift_id ?? '',
        ]);
    }

    public function saveDailyAttendance(string $date, array $rows, ?string $shiftId): void
    {
        foreach ($rows as $row) {
            if (empty($row['status'])) {
                continue;
            }

            Attendance::updateOrCreate(
                ['employee_id' => $row['employee_id'], 'date' => $date],
                [
                    'shift_id' => $shiftId,
                    'check_in' => $row['check_in'] ?: null,
                    'check_out' => $row['check_out'] ?: null,
                    'status' => $row['status'],
                    'overtime_hours' => $row['overtime_hours'] ?? 0,
                    'notes' => $row['notes'] ?? null,
                ]
            );
        }
    }

    // ── Shift Handovers ────────────────────────────────────────────────────

    public function listHandovers(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return ShiftHandover::with(['shift:id,name', 'fromEmployee:id,name', 'toEmployee:id,name'])
            ->when($filters['date'] ?? null, fn ($q, $v) => $q->whereDate('handover_date', $v))
            ->when($filters['shift_id'] ?? null, fn ($q, $v) => $q->where('shift_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('handover_date')
            ->orderByDesc('handover_time')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createHandover(array $data): ShiftHandover
    {
        return ShiftHandover::create($data + ['status' => HandoverStatus::Pending->value]);
    }

    public function acceptHandover(string $id): ShiftHandover
    {
        $handover = ShiftHandover::findOrFail($id);
        $handover->update(['status' => HandoverStatus::Accepted]);

        return $handover;
    }

    // ── Leaves ─────────────────────────────────────────────────────────────

    public function listLeaves(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Leave::with('employee:id,name,dept')
            ->when($filters['employee_id'] ?? null, fn ($q, $v) => $q->where('employee_id', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('from_date')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createLeave(array $data): Leave
    {
        $data['days'] = Carbon::parse($data['from_date'])->diffInDays(Carbon::parse($data['to_date'])) + 1;
        $data['status'] = LeaveStatus::Pending->value;

        return Leave::create($data);
    }

    public function approveLeave(string $id): Leave
    {
        $leave = Leave::findOrFail($id);
        $leave->update(['status' => LeaveStatus::Approved]);

        return $leave;
    }

    public function rejectLeave(string $id): Leave
    {
        $leave = Leave::findOrFail($id);
        $leave->update(['status' => LeaveStatus::Rejected]);

        return $leave;
    }

    // ── Payroll ────────────────────────────────────────────────────────────

    public function listPayroll(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        $paginator = Payroll::with('employee:id,name,dept,position')
            ->when($filters['month'] ?? null, fn ($q, $v) => $q->where('month', $v))
            ->when($filters['year'] ?? null, fn ($q, $v) => $q->where('year', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->getCollection()->transform(function ($payroll) {
            $payroll->attendance_summary = $this->getAttendanceSummary(
                $payroll->employee_id,
                $payroll->month,
                $payroll->year
            );

            return $payroll;
        });

        return $paginator;
    }

    public function getAttendanceSummary(string $employeeId, int $month, int $year): array
    {
        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $end = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $records = Attendance::where('employee_id', $employeeId)
            ->whereBetween('date', [$start, $end])
            ->get(['status', 'overtime_hours']);

        return [
            'present' => $records->where('status', AttendanceStatus::Present->value)->count(),
            'absent' => $records->where('status', AttendanceStatus::Absent->value)->count(),
            'late' => $records->where('status', AttendanceStatus::Late->value)->count(),
            'half_day' => $records->where('status', AttendanceStatus::HalfDay->value)->count(),
            'on_leave' => $records->where('status', AttendanceStatus::OnLeave->value)->count(),
            'overtime_hours' => (float) $records->sum('overtime_hours'),
            'recorded_days' => $records->count(),
        ];
    }

    public function getDepartments(): array
    {
        $raw = $this->settings->get('hr_departments', 'العيادة,التمريض,الإدارة,المالية,المخزن,المختبر,الاستقبال,الصيانة,الأمن,الخدمات');

        return array_filter(array_map('trim', explode(',', $raw)));
    }

    private function computeFromAttendance(float $base, array $summary): array
    {
        $workingDays = (int) $this->settings->get('hr_working_days', 26);
        $hoursPerDay = (int) $this->settings->get('hr_hours_per_day', 8);
        $overtimeMult = (float) $this->settings->get('hr_overtime_multiplier', 1.5);
        $latePct = (float) $this->settings->get('hr_late_deduction_pct', 25) / 100;
        $halfDayPct = (float) $this->settings->get('hr_half_day_deduction_pct', 50) / 100;

        $dailyRate = $workingDays > 0 ? $base / $workingDays : 0;
        $hourlyRate = $hoursPerDay > 0 ? $dailyRate / $hoursPerDay : 0;

        $deductions = round(
            ($summary['absent'] * $dailyRate) +
            ($summary['half_day'] * $dailyRate * $halfDayPct) +
            ($summary['late'] * $dailyRate * $latePct),
            2
        );

        $overtimePay = round($summary['overtime_hours'] * $hourlyRate * $overtimeMult, 2);

        return ['overtime_pay' => $overtimePay, 'deductions' => $deductions];
    }

    public function generatePayroll(int $month, int $year): int
    {
        $employees = Employee::where('status', EmployeeStatus::Active)->get(['id', 'base_salary', 'allowances']);
        $created = 0;

        foreach ($employees as $emp) {
            $exists = Payroll::where('employee_id', $emp->id)
                ->where('month', $month)
                ->where('year', $year)
                ->exists();

            if (! $exists) {
                $base = (float) $emp->base_salary;
                $allowances = (float) $emp->allowances;

                $summary = $this->getAttendanceSummary($emp->id, $month, $year);
                $computed = $this->computeFromAttendance($base, $summary);

                Payroll::create([
                    'employee_id' => $emp->id,
                    'month' => $month,
                    'year' => $year,
                    'base_salary' => $base,
                    'allowances' => $allowances,
                    'overtime_pay' => $computed['overtime_pay'],
                    'deductions' => $computed['deductions'],
                    'net_salary' => $base + $allowances + $computed['overtime_pay'] - $computed['deductions'],
                    'status' => PayrollStatus::Draft->value,
                ]);

                $created++;
            }
        }

        return $created;
    }

    public function recalculatePayroll(string $id): Payroll
    {
        $payroll = Payroll::findOrFail($id);
        $base = (float) $payroll->base_salary;
        $allowances = (float) $payroll->allowances;

        $summary = $this->getAttendanceSummary($payroll->employee_id, $payroll->month, $payroll->year);
        $computed = $this->computeFromAttendance($base, $summary);

        $payroll->update([
            'overtime_pay' => $computed['overtime_pay'],
            'deductions' => $computed['deductions'],
            'net_salary' => $base + $allowances + $computed['overtime_pay'] - $computed['deductions'],
        ]);

        return $payroll;
    }

    public function updatePayroll(string $id, array $data): Payroll
    {
        $payroll = Payroll::findOrFail($id);
        $net = (float) ($data['base_salary'] ?? $payroll->base_salary)
            + (float) ($data['allowances'] ?? $payroll->allowances)
            + (float) ($data['overtime_pay'] ?? $payroll->overtime_pay)
            - (float) ($data['deductions'] ?? $payroll->deductions);

        $data['net_salary'] = $net;
        $payroll->update($data);

        return $payroll;
    }

    public function approvePayroll(string $id): Payroll
    {
        $payroll = Payroll::findOrFail($id);
        $payroll->update(['status' => PayrollStatus::Approved]);

        return $payroll;
    }

    public function markPayrollPaid(string $id): Payroll
    {
        $payroll = Payroll::findOrFail($id);
        $payroll->update(['status' => PayrollStatus::Paid, 'paid_at' => now()]);

        return $payroll;
    }
}
