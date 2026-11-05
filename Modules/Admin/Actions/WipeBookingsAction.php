<?php

namespace Modules\Admin\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingService;
use Modules\Clinic\Models\ClinicSheet;
use Modules\Clinic\Models\MedicalExamination;
use Modules\Doctor\Models\BookingDoctorDelegation;
use Modules\Doctor\Models\DoctorEntitlement;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Labs\Models\DiagnosticResult;
use Modules\Surgery\Models\Surgery;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class WipeBookingsAction
{
    /**
     * Delete all bookings and all associated clinical, financial, and media reflections.
     */
    public function execute(): int
    {
        return DB::transaction(function () {
            $totalBookings = Booking::count();

            $bookingIds = Booking::pluck('id')->all();
            // Remove accounting children before the booking cleanup. This
            // also clears accounting-only history when there are no bookings.
            DB::table('journal_entries')->delete();
            DB::table('treasury_entries')->delete();
            DB::table('doctor_debt_settlements')->delete();
            DB::table('monthly_settlements')->delete();
            DB::table('accounting_alignment_log')->delete();
            DB::table('dr_payments')->delete();
            DB::table('insurance_claims')->delete();
            // Insurance collection audit trail, approvals and day closures.
            DB::table('activity_logs')->where('module', 'insurance_collection')->delete();
            foreach (['insurance_audit_logs', 'insurance_collection_approvals', 'insurance_collection_closures', 'patient_cash_entries'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }
            // Purchases (returns are cancelled invoices) and supplier payments.
            DB::table('supplier_payments')->delete();
            DB::table('purchase_invoice_items')->delete();
            DB::table('purchase_invoices')->delete();
            DB::table('suppliers')->update(['balance' => 0]);

            // 1. Spatie Media Library files associated with Booking and related clinical models
            $modelTypes = [
                Booking::class,
                ClinicSheet::class,
                MedicalExamination::class,
                DiagnosticResult::class,
                Surgery::class,
            ];

            Media::query()
                ->whereIn('model_type', $modelTypes)
                ->get()
                ->each(function (Media $media) {
                    try {
                        $media->delete();
                    } catch (\Throwable $e) {
                        Log::warning("Failed to delete media ID {$media->id}: ".$e->getMessage());
                    }
                });

            // 2. Doctor delegations
            BookingDoctorDelegation::query()->delete();

            // 3. Doctor entitlements
            DoctorEntitlement::query()->delete();

            // 4. Insurance claims
            InsuranceClaim::whereIn('booking_id', $bookingIds)->delete();

            // 5. Surgeries
            Surgery::whereIn('booking_id', $bookingIds)->delete();

            // 6. Diagnostic results
            DiagnosticResult::whereIn('booking_id', $bookingIds)->delete();

            // 7. Medical examinations
            MedicalExamination::whereIn('booking_id', $bookingIds)->delete();

            // 8. Clinic sheets
            ClinicSheet::whereIn('booking_id', $bookingIds)->delete();

            // 9. Booking services
            BookingService::whereIn('booking_id', $bookingIds)->delete();

            // 10. Activity logs for bookings
            DB::table('activity_logs')
                ->where('module', 'booking')
                ->orWhereIn('record_id', $bookingIds)
                ->delete();

            // 11. Delete all bookings
            Booking::query()->delete();

            return $totalBookings;
        });
    }
}
