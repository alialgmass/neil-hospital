<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repairs the doctor debt ledger corrupted by the pay screen re-pricing a
 * booking from its payment amount.
 *
 * PayBookingController used to set price = paid_amount and derive netDue from
 * that same payment, so a booking created at its placeholder price (222) and
 * then collected in full (10,000) had its debt computed from two different
 * prices. The running doctor_debt_balance was inflated without any matching
 * 'incurred' ledger row, and settleDebt() then settled the inflated figure
 * and wrote a 'settled' row for it.
 *
 * The result on the affected case: 222 was ever incurred, but 1,526 was
 * recorded as settled, so the claims report kept showing a
 * "خصم مديونية الطبيب" line of 1,526 against a case the patient paid in full.
 *
 * Two invariants are restored here, per doctor:
 *   1. total settled can never exceed total incurred (it can only ever be
 *      drawn from debt that was actually booked);
 *   2. the running balance equals incurred minus settled.
 *
 * Debt on a booking that has since been collected in full is voided outright,
 * matching Doctor::releaseDebtForBooking() — the money arrived, so nothing is
 * owed back.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $doctors = DB::table('doctors')->pluck('id');

            foreach ($doctors as $doctorId) {
                $incurred = (float) DB::table('doctor_debt_settlements')
                    ->where('doctor_id', $doctorId)
                    ->where('type', 'incurred')
                    ->sum('amount');

                // 1. Trim settlements that outran what was ever booked. Oldest
                //    first, so the reduction lands on the earliest rows.
                $settledRows = DB::table('doctor_debt_settlements')
                    ->where('doctor_id', $doctorId)
                    ->where('type', 'settled')
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->get(['id', 'amount']);

                $budget = $incurred;

                foreach ($settledRows as $row) {
                    if ($budget <= 0) {
                        DB::table('doctor_debt_settlements')->where('id', $row->id)->delete();

                        continue;
                    }

                    $amount = (float) $row->amount;

                    if ($amount <= $budget) {
                        $budget -= $amount;

                        continue;
                    }

                    if ($budget <= 0.001) {
                        DB::table('doctor_debt_settlements')->where('id', $row->id)->delete();

                        continue;
                    }

                    DB::table('doctor_debt_settlements')
                        ->where('id', $row->id)
                        ->update(['amount' => round($budget, 2)]);

                    $budget = 0.0;
                }

                // 2. Void debt booked against bookings that were later
                //    collected in full — the premise for the debt is gone.
                $paidBookingIds = DB::table('bookings')
                    ->where('pay_status', 'paid')
                    ->where('paid_amount', '>', 0)
                    ->pluck('id')
                    ->all();

                if ($paidBookingIds !== []) {
                    // Both directions go. The 'incurred' rows are the debt
                    // itself, and the 'settled' rows drawn against the same
                    // case are what the claims report deducts per booking —
                    // leaving either behind keeps a
                    // "خصم مديونية الطبيب" line on a case that was paid in
                    // full. Debt belonging to *other*, still-unpaid bookings
                    // is untouched.
                    DB::table('doctor_debt_settlements')
                        ->where('doctor_id', $doctorId)
                        ->whereIn('booking_id', $paidBookingIds)
                        ->delete();
                }

                // 3. Re-derive the running balance from the ledger so it can
                //    never drift from its own history again.
                $balance = (float) DB::table('doctor_debt_settlements')
                    ->where('doctor_id', $doctorId)
                    ->where('type', 'incurred')
                    ->sum('amount')
                    - (float) DB::table('doctor_debt_settlements')
                        ->where('doctor_id', $doctorId)
                        ->where('type', 'settled')
                        ->sum('amount');

                DB::table('doctors')
                    ->where('id', $doctorId)
                    ->update(['doctor_debt_balance' => round(max(0.0, $balance), 2)]);
            }
        });
    }

    public function down(): void
    {
        // Ledger repair is not reversible: the trimmed and voided rows encoded
        // figures that never reconciled. Re-deriving balances is idempotent,
        // but the deleted rows cannot be reconstructed.
    }
};
