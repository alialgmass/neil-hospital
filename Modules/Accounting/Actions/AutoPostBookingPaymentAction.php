<?php

namespace Modules\Accounting\Actions;

use App\Enums\Department;
use Illuminate\Support\Facades\Log;
use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\AccountGroup;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Enums\TreasuryType;
use Modules\Accounting\Exceptions\AccountingException;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\JournalService;
use Modules\Accounting\Services\TreasuryService;
use Modules\Booking\Enums\PayMethod;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;

class AutoPostBookingPaymentAction
{
    public function __construct(
        private readonly TreasuryService $treasuryService,
        private readonly JournalService $journalService,
        private readonly AccountResolver $accountResolver,
    ) {}

    /**
     * Post a booking payment to the treasury and journal.
     *
     * Idempotent: posts only the portion of `paid_amount` not already
     * reflected in journal_entries for this booking. This also makes the
     * no-amount "full re-post on completion" call site (UpdateBookingStatusAction)
     * a safe no-op once payments were already posted incrementally.
     *
     * Insurance-method bookings post nothing here — that side of the ledger
     * is owned entirely by AutoPostInsuranceClaimAction's Dr 1030/Cr 4110 →
     * Dr 1010/Cr 1030 lifecycle.
     *
     * @param  float|null  $amount  Defaults to booking's paid_amount.
     */
    public function execute(Booking $booking, ?float $amount = null): void
    {
        if ($booking->pay_method === PayMethod::Insurance) {
            return;
        }

        $paidAmount = (float) $booking->paid_amount;

        $alreadyPosted = (float) JournalEntry::whereIn('source', [
            JournalSource::BOOKING->value,
            JournalSource::AUTO_BOOKING->value,
        ])
            ->where('reference', $booking->file_no)
            ->whereNull('reversed_at')
            ->sum('amount');

        $unpostedRemainder = round($paidAmount - $alreadyPosted, 2);

        // $amount, when given, is the delta for this specific payment call
        // (PayBookingController). When omitted (UpdateBookingStatusAction's
        // completion call), post whatever remainder hasn't been posted yet —
        // which is naturally zero once PayBookingController already posted
        // the booking's full paid_amount incrementally.
        $toPost = round(min($amount ?? $unpostedRemainder, $unpostedRemainder), 2);

        if ($toPost <= 0) {
            return;
        }

        $date = $booking->visit_date->toDateString();

        // 1. Treasury entry (cash movement)
        $this->treasuryService->record([
            'type' => TreasuryType::In,
            'description' => "دفعة حجز: {$booking->file_no} — {$booking->patient_name}",
            'amount' => $toPost,
            'date' => $date,
            'source' => JournalSource::BOOKING,
            'booking_id' => $booking->id,
        ]);

        // 2. Determine debit account (cash/bank)
        $debitAccId = $this->accountResolver->id($this->debitAccountCode($booking));

        // 3. Determine credit account (revenue by dept or service-specific)
        $creditAccId = $this->findRevenueAccountId($booking);

        $this->journalService->record([
            'date' => $date,
            'description' => "إيراد حجز: {$booking->file_no} — {$booking->service_name}",
            'debit_account_id' => $debitAccId,
            'credit_account_id' => $creditAccId,
            'amount' => $toPost,
            'source' => JournalSource::BOOKING,
            'reference' => $booking->file_no,
            'idempotency_key' => "booking_payment:{$booking->file_no}:{$paidAmount}",
            'cost_center' => $this->costCenter($booking->dept),
        ]);
    }

    private function debitAccountCode(Booking $booking): AccountCode
    {
        return match ($booking->pay_method) {
            PayMethod::Card, PayMethod::Transfer => AccountCode::BANK,
            default => AccountCode::CASH,
        };
    }

    private function findRevenueAccountId(Booking $booking): string
    {
        // Service-specific revenue account takes priority — but only if it's
        // a valid, active, postable revenue account. Otherwise fall back to
        // the dept default and log the misconfiguration instead of posting
        // to a bad account.
        if ($booking->service_id) {
            $serviceAccountId = $booking->service?->revenue_account_id
                ?? Service::whereKey($booking->service_id)->value('revenue_account_id');

            if ($serviceAccountId) {
                try {
                    $account = $this->accountResolver->mustBePostableAndActive($serviceAccountId);

                    if ($account->group !== AccountGroup::Revenues) {
                        throw new AccountingException("Service revenue_account_id {$account->code} is not a revenue account.");
                    }

                    return $account->id;
                } catch (AccountingException $e) {
                    Log::warning('Invalid service.revenue_account_id override — falling back to dept revenue account.', [
                        'service_id' => $booking->service_id,
                        'booking_file_no' => $booking->file_no,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Fall back to dept-level account per guide chart
        return $this->accountResolver->id(AccountCode::deptRevenueCode($booking->dept));
    }

    private function costCenter(Department $dept): CostCenter
    {
        return match ($dept) {
            Department::Clinic => CostCenter::Clinic,
            Department::Labs => CostCenter::Lab,
            Department::Surgery => CostCenter::Surgery,
            Department::Lasik => CostCenter::Lasik,
            Department::Laser => CostCenter::Laser,
        };
    }
}
