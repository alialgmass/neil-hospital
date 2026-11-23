<?php

namespace Modules\Accounting\Actions;

use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Enums\TreasuryType;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\JournalNarration;
use Modules\Accounting\Services\JournalService;
use Modules\Accounting\Services\TreasuryService;
use Modules\Booking\Enums\PayMethod;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;

/**
 * Splits a fixed "development treasury" share (رسوم خزنة التطوير) out of a
 * cash booking's collected amount into a separate treasury account (1011),
 * per the service's `dev_treasury_fee`.
 *
 * This is a pure transfer between two asset accounts — Dr 1011 (خزنة
 * التطوير) / Cr 1010 (الخزنة الرئيسية) — it does not touch revenue: the
 * booking's full price is still recognised as revenue by
 * AutoPostBookingPaymentAction, this only re-splits which physical till
 * the cash sits in.
 *
 * Also recorded on the treasury screen as an outflow of the main till.
 *
 * Cash-only (private cases — never insurance/contract), mirroring the account's own note in AccountCode::DEVELOPMENT_FUND
 * ("الاستقبال: 50 ج من كل خدمة نقدية"). Posted once per booking regardless
 * of how many partial payments it takes to pay it off (idempotency key keyed
 * by file_no, not by amount).
 */
class AutoPostDevelopmentFeeAction
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly AccountResolver $accountResolver,
        private readonly TreasuryService $treasuryService,
    ) {}

    public function execute(Booking $booking): void
    {
        if ($booking->pay_method !== PayMethod::Cash) {
            return;
        }

        $fee = $this->resolveFee($booking);

        if ($fee === null || $fee <= 0) {
            return;
        }

        $key = "development_fee:{$booking->file_no}";

        if (JournalEntry::where('idempotency_key', $key)->whereNull('reversed_at')->exists()) {
            return;
        }

        $developmentFundId = $this->accountResolver->id(AccountCode::DEVELOPMENT_FUND);
        $cashId = $this->accountResolver->id(AccountCode::CASH);
        $date = $booking->visit_date->toDateString();
        $description = JournalNarration::make('رسوم خزنة التطوير (تحويل من الخزنة الرئيسية)', [
            'ملف' => $booking->file_no,
            'المريض' => $booking->patient_name,
            'الخدمة' => $booking->service_name,
            'القسم' => $booking->dept,
            'الرسم' => JournalNarration::money($fee),
            'تاريخ الزيارة' => $date,
        ]);

        // The transfer leaves the main till (1010), so it must show on the
        // treasury screen as an outflow too — otherwise the screen and the
        // ledger balance of 1010 disagree by the fee.
        $this->treasuryService->record([
            'type' => TreasuryType::Out,
            'description' => $description,
            'amount' => $fee,
            'date' => $date,
            'source' => JournalSource::AUTO_BOOKING,
            'reference_no' => $booking->file_no,
            'booking_id' => $booking->id,
        ]);

        $this->journalService->record([
            'date' => $date,
            'description' => $description,
            'debit_account_id' => $developmentFundId,
            'credit_account_id' => $cashId,
            'amount' => $fee,
            'source' => JournalSource::AUTO_BOOKING,
            'reference' => $booking->file_no,
            'idempotency_key' => $key,
            'cost_center' => CostCenter::Admin,
        ]);
    }

    private function resolveFee(Booking $booking): ?float
    {
        if ($booking->service_id === null) {
            return null;
        }

        $fee = $booking->service?->dev_treasury_fee
            ?? Service::whereKey($booking->service_id)->value('dev_treasury_fee');

        return $fee !== null ? (float) $fee : null;
    }
}
