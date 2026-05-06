<?php

namespace Modules\Accounting\Actions;

use App\Enums\Department;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Services\JournalService;

class AutoPostDoctorDuesAction
{
    public function __construct(private readonly JournalService $journalService) {}

    /**
     * Record doctor dues accrual for a shift or booking.
     * Dr 5110 (Clinic) or 5120 (Surgery/Lasik) / Cr 2010 (Doctor Payables)
     */
    public function execute(
        Department $dept,
        float $amount,
        string $doctorName,
        string $reference,
        ?string $date = null,
    ): void {
        if ($amount <= 0) {
            return;
        }

        $expenseCode = match ($dept) {
            Department::Surgery, Department::Lasik => '5120',
            default => '5110',
        };

        $expenseId = DB::table('accounts')->where('code', $expenseCode)->value('id');
        $payableId = DB::table('accounts')->where('code', '2010')->value('id');

        if (! $expenseId || ! $payableId) {
            return;
        }

        $this->journalService->record([
            'date' => $date ?? now()->toDateString(),
            'description' => "نصيب الطبيب: {$doctorName} — ".$dept->label(),
            'debit_account_id' => $expenseId,
            'credit_account_id' => $payableId,
            'amount' => $amount,
            'source' => JournalSource::DOCTOR_SHIFT,
            'reference' => $reference,
            'cost_center' => CostCenter::Doctors,
        ]);
    }
}
