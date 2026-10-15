<?php

namespace Modules\Insurance\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Actions\AutoPostInsuranceClaimAction;
use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\JournalNarration;
use Modules\Accounting\Services\JournalService;
use Modules\Admin\Services\ActivityLogService;
use Modules\Insurance\Enums\DepositDecision;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\PendingDeposit;
use Modules\Insurance\States\SubmittedState;

class ConvertPendingDepositAction
{
    public function __construct(
        private readonly JournalService $journal,
        private readonly AccountResolver $accounts,
        private readonly AutoPostInsuranceClaimAction $autoPost,
        private readonly ActivityLogService $activityLog,
    ) {}

    /**
     * Approved deposit → a submitted claim on the company, and the deposit is
     * refunded to the patient (Dr 2050 / Cr cash). Runs at most once.
     *
     * @throws ValidationException
     */
    public function execute(PendingDeposit $deposit, int $userId): InsuranceClaim
    {
        return DB::transaction(function () use ($deposit, $userId): InsuranceClaim {
            $deposit = PendingDeposit::whereKey($deposit->id)->lockForUpdate()->firstOrFail();

            if ($deposit->decision !== DepositDecision::Approved || $deposit->converted) {
                throw ValidationException::withMessages([
                    'deposit' => 'التحويل متاح فقط لأمانة وافقت عليها الشركة ولم تُحوَّل من قبل.',
                ]);
            }

            $booking = $deposit->booking;
            if ($booking->insuranceClaim()->exists()) {
                throw ValidationException::withMessages(['deposit' => 'الحالة دي ليها مطالبة بالفعل.']);
            }

            $claimAmount = (float) ($booking->ins_amount > 0 ? $booking->ins_amount : $booking->price);

            $claim = InsuranceClaim::create([
                'booking_id' => $booking->id,
                'insurance_company_id' => $deposit->insurance_company_id,
                'service_id' => $booking->service_id,
                'patient_name' => $booking->patient_name,
                'file_no' => $booking->file_no,
                'service_name' => $booking->service_name,
                'invoice_amount' => (float) $booking->price,
                'insurance_share' => $claimAmount,
                'patient_share' => max(0, (float) $booking->price - $claimAmount),
                'status' => SubmittedState::$name,
                'service_date' => $booking->visit_date?->toDateString() ?? now()->toDateString(),
                'claim_date' => now()->toDateString(),
                'submission_date' => now()->toDateString(),
                'claim_reference' => 'CONV-'.strtoupper(substr($deposit->id, -8)),
                'created_by' => $userId,
            ]);

            $this->autoPost->recognize($claim);

            $this->journal->record([
                'date' => now()->toDateString(),
                'description' => JournalNarration::make('رد أمانة للمريض بعد موافقة الشركة', [
                    'المريض' => $booking->patient_name,
                    'ملف' => $booking->file_no,
                    'المبلغ' => JournalNarration::money($deposit->amount),
                ]),
                'debit_account_id' => $this->accounts->id(AccountCode::PATIENT_ADVANCES),
                'credit_account_id' => $this->accounts->id(AccountCode::CASH),
                'amount' => $deposit->amount,
                'source' => JournalSource::INSURANCE_DEPOSIT,
                'reference' => $booking->file_no,
                'idempotency_key' => "insurance_deposit_refund:{$deposit->id}",
            ]);

            $deposit->update(['converted' => true, 'refunded' => true, 'claim_id' => $claim->id]);

            $this->activityLog->log(
                action: 'deposit_convert',
                module: 'insurance_collection',
                recordId: $claim->id,
                description: "تحويل أمانة {$booking->patient_name} لمطالبة واسترداد ".number_format($deposit->amount).' ج.م للمريض',
            );

            return $claim;
        });
    }
}
