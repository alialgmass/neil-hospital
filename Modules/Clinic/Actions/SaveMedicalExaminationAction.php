<?php

namespace Modules\Clinic\Actions;

use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Clinic\DTOs\MedicalExaminationData;
use Modules\Clinic\Enums\ExaminationStatus;
use Modules\Clinic\Models\Diagnosis;
use Modules\Clinic\Models\MedicalExamination;

class SaveMedicalExaminationAction
{
    public function __construct(private readonly ActivityLogService $activityLog) {}

    /**
     * Create (when $examination is null) or update the booking's examination,
     * replacing its per-eye findings, diagnoses and investigations in one
     * transaction. Finalizing stamps who/when and locks the record.
     */
    public function execute(
        Booking $booking,
        MedicalExaminationData $data,
        User $user,
        ?MedicalExamination $examination = null,
    ): MedicalExamination {
        $examination = DB::transaction(function () use ($booking, $data, $user, $examination) {
            if ($examination === null) {
                $this->ensureBookingHasNoExamination($booking);

                $examination = new MedicalExamination([
                    'booking_id' => $booking->id,
                    'status' => ExaminationStatus::Draft,
                    'examined_at' => now(),
                    'created_by' => $user->id,
                ]);
            }

            $examination->fill([
                ...$data->attributes,
                'doctor_id' => $data->doctorId ?? $examination->doctor_id ?? $booking->doctor_id,
                'updated_by' => $user->id,
            ]);

            if ($data->finalize) {
                $examination->fill([
                    'status' => ExaminationStatus::Finalized,
                    'finalized_at' => now(),
                    'finalized_by' => $user->id,
                ]);
            }

            $examination->save();

            foreach ($data->eyes as $eye => $values) {
                $examination->eyes()->updateOrCreate(['eye' => $eye], $values);
            }

            $this->syncDiagnoses($examination, $data->diagnoses);
            $this->syncInvestigations($examination, $data->investigations);

            return $examination;
        });

        $this->activityLog->log(
            action: $data->finalize ? 'medical_examination_finalized' : 'medical_examination_saved',
            module: 'clinic',
            recordId: $examination->id,
            description: ($data->finalize ? 'اعتماد' : 'حفظ')." فحص طبي للحجز: {$booking->file_no}",
        );

        return $examination;
    }

    private function ensureBookingHasNoExamination(Booking $booking): void
    {
        $exists = MedicalExamination::where('booking_id', $booking->id)->lockForUpdate()->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'booking' => 'يوجد فحص طبي مسجل لهذه الزيارة بالفعل.',
            ]);
        }
    }

    /**
     * @param  array<int, array{diagnosis_id?: ?string, name?: ?string, eye?: ?string, notes?: ?string}>  $rows
     */
    private function syncDiagnoses(MedicalExamination $examination, array $rows): void
    {
        $examination->diagnoses()->delete();

        foreach ($rows as $row) {
            $diagnosisId = $row['diagnosis_id'] ?? null;

            // A diagnosis typed by the doctor that isn't in the catalog yet is added to it.
            if (! $diagnosisId) {
                $diagnosisId = Diagnosis::firstOrCreate(['name' => trim((string) $row['name'])])->id;
            }

            $examination->diagnoses()->create([
                'diagnosis_id' => $diagnosisId,
                'eye' => $row['eye'] ?? null,
                'notes' => $row['notes'] ?? null,
            ]);
        }
    }

    /**
     * @param  array<int, array{service_id: string, eye?: ?string, notes?: ?string}>  $rows
     */
    private function syncInvestigations(MedicalExamination $examination, array $rows): void
    {
        $examination->investigations()->delete();

        $serviceNames = Service::whereIn('id', array_column($rows, 'service_id'))->pluck('name', 'id');

        foreach ($rows as $row) {
            $examination->investigations()->create([
                'service_id' => $row['service_id'],
                'name' => $serviceNames[$row['service_id']] ?? '',
                'eye' => $row['eye'] ?? null,
                'notes' => $row['notes'] ?? null,
            ]);
        }
    }
}
