<?php

namespace Modules\Insurance\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Enums\TreasuryType;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\JournalService;
use Modules\Accounting\Services\TreasuryService;
use Modules\Insurance\Models\PatientCashEntry;
use Modules\Insurance\Services\InsuranceAuditService;

class CollectPatientCashEntryAction
{
    public function __construct(private readonly TreasuryService $treasury, private readonly JournalService $journal, private readonly AccountResolver $accounts, private readonly InsuranceAuditService $audit) {}

    public function execute(PatientCashEntry $entry, User $user): PatientCashEntry
    {
        return DB::transaction(function () use ($entry, $user): PatientCashEntry {
            $entry = PatientCashEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if ($entry->status !== 'due') {
                throw ValidationException::withMessages(['entry' => 'تم تحصيل هذا المبلغ من قبل.']);
            }
            $entry->update(['status' => 'paid', 'collected_by' => $user->id, 'collected_at' => now()]);
            $booking = $entry->booking;
            $amount = (float) $entry->amount;
            $this->treasury->record(['type' => TreasuryType::In, 'description' => $entry->description, 'amount' => $amount, 'date' => today(), 'source' => JournalSource::BOOKING, 'booking_id' => $booking->id]);
            $this->journal->record(['date' => today(), 'description' => $entry->description, 'debit_account_id' => $this->accounts->id(AccountCode::CASH), 'credit_account_id' => $this->accounts->id(AccountCode::deptRevenueCode($booking->dept)), 'amount' => $amount, 'source' => JournalSource::BOOKING, 'reference' => $booking->file_no, 'idempotency_key' => "patient_cash:{$entry->id}"]);
            $this->audit->record('cash_collection', 'تحصيل نقدي للمريض: '.number_format($amount), $user);

            return $entry;
        });
    }
}
