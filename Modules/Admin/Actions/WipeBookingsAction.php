<?php

namespace Modules\Admin\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\TreasuryEntry;
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

            if ($totalBookings === 0) {
                return 0;
            }

            $bookingIds = Booking::pluck('id')->all();
            $fileNumbers = Booking::whereNotNull('file_no')->pluck('file_no')->all();

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
            BookingDoctorDelegation::whereIn('booking_id', $bookingIds)->delete();

            // 3. Doctor entitlements
            DoctorEntitlement::whereIn('booking_id', $bookingIds)->delete();

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

            // 10. Treasury entries linked to bookings
            TreasuryEntry::whereIn('booking_id', $bookingIds)
                ->orWhereIn('source', [
                    JournalSource::BOOKING->value,
                    JournalSource::AUTO_BOOKING->value,
                ])
                ->delete();

            // 11. Journal entries linked to bookings (by reference file numbers or booking source or idempotency keys)
            if (! empty($fileNumbers)) {
                JournalEntry::whereIn('reference', $fileNumbers)
                    ->orWhere(function ($q) use ($fileNumbers) {
                        foreach ($fileNumbers as $fileNo) {
                            $q->orWhere('reference', 'REV-'.$fileNo);
                        }
                    })
                    ->orWhereIn('source', [
                        JournalSource::BOOKING->value,
                        JournalSource::AUTO_BOOKING->value,
                    ])
                    ->orWhere(function ($q) {
                        $q->where('idempotency_key', 'like', 'booking_%')
                            ->orWhere('idempotency_key', 'like', 'doctor_entitlement:%')
                            ->orWhere('idempotency_key', 'like', 'insurance_claim:%');
                    })
                    ->delete();
            } else {
                JournalEntry::whereIn('source', [
                    JournalSource::BOOKING->value,
                    JournalSource::AUTO_BOOKING->value,
                ])->delete();
            }

            // 12. Activity logs for bookings
            DB::table('activity_logs')
                ->where('subject_type', 'booking')
                ->orWhereIn('subject_id', $bookingIds)
                ->delete();

            // 13. Delete all bookings
            Booking::query()->delete();

            return $totalBookings;
        });
    }
}
