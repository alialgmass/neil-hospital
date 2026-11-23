<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Doctor\Actions\SyncBookingDoctorDuesAction;

/**
 * Data repair: doctor fees edited after a paid booking was created left the
 * ledger without the dues accrual the claims screen (which prices cases from
 * the live fees) shows — paying those dues turned the doctor's payable
 * account debit. Re-align every doctor's paid cash bookings once.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sync = app(SyncBookingDoctorDuesAction::class);

        foreach (DB::table('doctors')->pluck('id') as $doctorId) {
            try {
                DB::transaction(fn () => $sync->resyncForDoctor($doctorId));
            } catch (Throwable $e) {
                Log::warning("resync doctor dues: doctor {$doctorId} failed — {$e->getMessage()}");
            }
        }
    }

    public function down(): void
    {
        // Data repair — nothing to roll back.
    }
};
