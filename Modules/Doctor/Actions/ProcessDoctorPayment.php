<?php

namespace Modules\Doctor\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Services\JournalService;
use Modules\Accounting\Services\TreasuryService;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Models\DoctorPayment;

class ProcessDoctorPayment
{
    public function __construct(
        private readonly TreasuryService $treasuryService,
        private readonly JournalService $journalService,
    ) {}

    /**
     * Process a payment to a doctor and record accounting entries.
     */
    public function execute(Doctor $doctor, array $data): DoctorPayment
    {
        return DB::transaction(function () use ($doctor, $data) {
            // 1. Record the doctor payment
            $payment = DoctorPayment::create([
                'doctor_id' => $doctor->id,
                'amount' => $data['amount'],
                'period_from' => $data['period_from'],
                'period_to' => $data['period_to'],
                'paid_at' => now(),
                'method' => $data['method'] ?? 'cash',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // 2. Create Treasury Entry (Out)
            $this->treasuryService->record([
                'type' => 'out',
                'description' => "صرف مستحقات طبيب: {$doctor->name} — الفترة من {$data['period_from']} إلى {$data['period_to']}",
                'amount' => $data['amount'],
                'date' => now()->toDateString(),
                'source' => 'doctor_payment',
            ]);

            // 3. Create Journal Entry (Debit Expense: 3100 / Credit Cash: 1000)
            $expenseAccount = $this->findAccount('3100'); // مستحقات الأطباء
            $cashAccount = $this->findAccount($data['method'] === 'card' ? '1100' : '1000');

            if ($expenseAccount && $cashAccount) {
                $this->journalService->record([
                    'date' => now()->toDateString(),
                    'description' => "مستحقات طبيب: {$doctor->name} — الفترة من {$data['period_from']} إلى {$data['period_to']}",
                    'debit_account_id' => $expenseAccount,
                    'credit_account_id' => $cashAccount,
                    'amount' => $data['amount'],
                    'source' => 'doctor_payment',
                    'reference' => (string) $payment->id,
                ]);
            }

            return $payment;
        });
    }

    private function findAccount(string $code): ?string
    {
        return DB::table('accounts')->where('code', $code)->value('id');
    }
}
