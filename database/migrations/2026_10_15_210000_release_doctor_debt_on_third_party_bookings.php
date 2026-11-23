<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Voids doctor debt that was booked against insurance/contract bookings.
 *
 * Such a booking is settled by a third party through its claim, so its
 * uncollected amount is the insurer's receivable — never the doctor's debt.
 * Debt could still end up on them when a booking was created as unpaid cash
 * and later edited to insurance/contract.
 *
 * Per booking the 'incurred' rows are deleted and the doctor's running
 * balance is reduced by the same amount, clamped at zero.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $rows = DB::table('doctor_debt_settlements as s')
                ->join('bookings as b', 'b.id', '=', 's.booking_id')
                ->where('s.type', 'incurred')
                ->whereIn('b.pay_method', ['insurance', 'contract'])
                ->get(['s.id', 's.doctor_id', 's.amount']);

            foreach ($rows->groupBy('doctor_id') as $doctorId => $doctorRows) {
                $balance = (float) DB::table('doctors')->where('id', $doctorId)->value('doctor_debt_balance');

                DB::table('doctors')->where('id', $doctorId)->update([
                    'doctor_debt_balance' => round(max(0.0, $balance - (float) $doctorRows->sum('amount')), 2),
                ]);

                DB::table('doctor_debt_settlements')->whereIn('id', $doctorRows->pluck('id'))->delete();
            }
        });
    }

    public function down(): void
    {
        // Irreversible data repair.
    }
};
