<?php

use App\Enums\Department;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Accounting\Actions\AutoPostInsuranceClaimAction;
use Modules\Accounting\Enums\JournalSource;
use Modules\Booking\Enums\PayMethod;
use Modules\Booking\Models\Booking;
use Modules\Booking\States\CancelledState;
use Modules\Doctor\Actions\SyncDoctorEntitlementAction;
use Modules\Doctor\Enums\EntitlementStatus;
use Modules\Doctor\Models\DoctorEntitlement;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\States\DraftState;

/**
 * Data repair for two posting gaps:
 *
 * 1. Labs insurance bookings were saved with ins_amount = 0 (the booking form
 *    never computed the insurer's share for labs), so their claim carried a
 *    zero share and no receivable/revenue entry was ever posted.
 * 2. An insurance doctor entitlement paid out before its booking was
 *    completed was marked settled, and completion then skipped its accrual —
 *    the payout left the doctor's payable account in debit.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->repairLabsInsuranceShares();
        $this->repairSettledDoctorAccruals();
    }

    public function down(): void
    {
        // Data repair — the posted entries are reversed through the normal accounting flows.
    }

    private function repairLabsInsuranceShares(): void
    {
        $recognize = app(AutoPostInsuranceClaimAction::class);

        $bookings = DB::table('bookings')
            ->where('dept', Department::Labs->value)
            ->where('pay_method', PayMethod::Insurance->value)
            ->whereNotNull('ins_company_id')
            ->where('ins_amount', '<=', 0)
            ->where('price', '>', 0)
            ->where('status', '!=', CancelledState::$name)
            ->get(['id', 'file_no', 'ins_company_id', 'price', 'discount', 'paid_amount']);

        foreach ($bookings as $booking) {
            $claim = InsuranceClaim::where('booking_id', $booking->id)->first();

            if (! $claim || ! $claim->status->equals(DraftState::class) || (float) $claim->insurance_share > 0) {
                continue;
            }

            $coverage = DB::table('price_lists')
                ->where('ins_company_id', $booking->ins_company_id)
                ->where('is_active', true)
                ->value('ins_coverage')
                ?? DB::table('insurance_companies')->where('id', $booking->ins_company_id)->value('coverage_pct');

            $price = (float) $booking->price;
            $discount = (float) $booking->discount;
            $share = min(round($price * (float) $coverage / 100, 2), max(0.0, $price - $discount));
            $patientShare = max(0.0, round($price - $discount - $share, 2));

            // The patient already paid more than their share would be — the
            // case was effectively settled in cash; leave it for manual review.
            if ($share <= 0 || (float) $booking->paid_amount > $patientShare) {
                Log::warning("repair labs insurance share: skipped booking {$booking->file_no} (share {$share}, paid {$booking->paid_amount}).");

                continue;
            }

            try {
                DB::transaction(function () use ($booking, $claim, $share, $patientShare, $recognize): void {
                    DB::table('bookings')->where('id', $booking->id)->update(['ins_amount' => $share]);
                    $claim->update(['insurance_share' => $share, 'patient_share' => $patientShare]);
                    $recognize->recognize($claim->refresh());
                });
            } catch (Throwable $e) {
                Log::warning("repair labs insurance share: booking {$booking->file_no} failed — {$e->getMessage()}");
            }
        }
    }

    private function repairSettledDoctorAccruals(): void
    {
        $sync = app(SyncDoctorEntitlementAction::class);

        DoctorEntitlement::where('status', EntitlementStatus::Settled)
            ->whereNotNull('booking_id')
            ->orderBy('created_at')
            ->each(function (DoctorEntitlement $entitlement) use ($sync): void {
                $booking = Booking::find($entitlement->booking_id);

                if (! $booking || $booking->status instanceof CancelledState || ! $booking->canAccrueDoctorDues()) {
                    return;
                }

                $accrued = DB::table('journal_entries')
                    ->where('reference', $booking->file_no)
                    ->whereIn('source', [JournalSource::DOCTOR_SHIFT->value, JournalSource::INSURANCE_DOCTOR_PAYMENT->value])
                    ->whereNull('reversed_at')
                    ->whereNull('reversal_of_id')
                    ->exists();

                if ($accrued) {
                    return;
                }

                try {
                    $sync->ensureSettledAccrual($booking, $entitlement);
                } catch (Throwable $e) {
                    Log::warning("repair settled doctor accrual: booking {$booking->file_no} failed — {$e->getMessage()}");
                }
            });
    }
};
