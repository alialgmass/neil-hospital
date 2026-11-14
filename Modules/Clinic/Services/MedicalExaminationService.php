<?php

namespace Modules\Clinic\Services;

use App\Enums\Department;
use App\Enums\EyeSide;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Clinic\Enums\ContactLensType;
use Modules\Clinic\Enums\EyeDisease;
use Modules\Clinic\Enums\EyeSurgery;
use Modules\Clinic\Enums\GlassesUsage;
use Modules\Clinic\Enums\IopMethod;
use Modules\Clinic\Enums\SystemicDisease;
use Modules\Clinic\Http\Requests\SaveMedicalExaminationRequest;
use Modules\Clinic\Models\Diagnosis;
use Modules\Clinic\Models\MedicalExamination;
use Modules\Doctor\Models\Doctor;

class MedicalExaminationService
{
    /** Relations needed to render an examination (form, read-only view or print). */
    public const DISPLAY_RELATIONS = [
        'eyes',
        'diagnoses.diagnosis:id,name,code',
        'investigations',
        'doctor:id,name,specialty',
        'finalizedBy:id,name',
        'createdBy:id,name',
    ];

    public function loadForDisplay(MedicalExamination $examination): MedicalExamination
    {
        return $examination->load(self::DISPLAY_RELATIONS);
    }

    /**
     * Patient header shown on the form and report. Read from the booking —
     * patient data is never duplicated onto the examination.
     *
     * @return array<string, mixed>
     */
    public function patientSummary(Booking $booking): array
    {
        $booking->loadMissing('doctor:id,name');

        return [
            'booking_id' => $booking->id,
            'patient_name' => $booking->patient_name,
            'file_no' => $booking->file_no,
            'patient_age' => $booking->patient_age,
            'gender' => $booking->gender,
            'patient_phone' => $booking->patient_phone,
            'national_id' => $booking->national_id,
            'dept' => $booking->dept?->value,
            'dept_label' => $booking->dept?->label(),
            'visit_date' => $booking->visit_date?->toDateString(),
            'eye_side' => $booking->eye_side?->value,
            'doctor' => $booking->doctor ? ['id' => $booking->doctor->id, 'name' => $booking->doctor->name] : null,
        ];
    }

    /**
     * Examinations from the same patient's other visits, newest visit first.
     * There is no patients table — each booking carries its own file_no — so
     * a patient is matched by national ID when present, otherwise by name
     * and phone together.
     */
    public function previousExaminations(Booking $booking): Collection
    {
        return MedicalExamination::query()
            ->with(['booking:id,file_no,visit_date,dept', 'doctor:id,name', 'diagnoses.diagnosis:id,name'])
            ->whereIn('booking_id', $this->samePatientBookingIds($booking))
            ->where('booking_id', '!=', $booking->id)
            ->orderByDesc(Booking::select('visit_date')->whereColumn('bookings.id', 'medical_examinations.booking_id'))
            ->latest('examined_at')
            ->get(['id', 'booking_id', 'doctor_id', 'status', 'examined_at', 'finalized_at']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function samePatientBookingIds(Booking $booking): \Illuminate\Support\Collection
    {
        $query = Booking::query();

        if ($booking->national_id) {
            $query->where('national_id', $booking->national_id);
        } else {
            $query->where('patient_name', $booking->patient_name);

            $booking->patient_phone
                ? $query->where('patient_phone', $booking->patient_phone)
                : $query->whereNull('patient_phone');
        }

        return $query->pluck('id');
    }

    /**
     * Seed a brand-new examination from what the clinic sheet (initial
     * assessment) already recorded for this visit, so nothing is typed twice.
     *
     * @return array<string, mixed>
     */
    public function prefillFromClinicSheet(Booking $booking): array
    {
        $sheet = $booking->clinicSheet;

        if (! $sheet) {
            return [];
        }

        $visualExam = $sheet->visual_exam ?? [];

        return array_filter([
            'chief_complaint' => $sheet->chief_complaint,
            'allergies' => $sheet->allergies_status === 'yes' ? $sheet->allergies_specify : null,
            'eyes' => [
                EyeSide::OD->value => array_filter([
                    'ucva' => $visualExam['uncorrected']['right'] ?? $sheet->visual_acuity_od,
                    'cva' => $visualExam['correction']['right'] ?? null,
                    'iop' => $sheet->iop_od,
                ], fn ($value) => $value !== null && $value !== ''),
                EyeSide::OS->value => array_filter([
                    'ucva' => $visualExam['uncorrected']['left'] ?? $sheet->visual_acuity_os,
                    'cva' => $visualExam['correction']['left'] ?? null,
                    'iop' => $sheet->iop_os,
                ], fn ($value) => $value !== null && $value !== ''),
            ],
        ], fn ($value) => $value !== null && $value !== '');
    }

    /** The doctor record linked to the logged-in user, if any — the natural examiner. */
    public function doctorForUser(User $user): ?Doctor
    {
        return Doctor::where('user_id', $user->id)->first(['id', 'name']);
    }

    /**
     * Select/checkbox sources for the form.
     *
     * @return array<string, mixed>
     */
    public function formOptions(): array
    {
        return [
            'eye_sides' => array_map(
                fn (EyeSide $side) => ['value' => $side->value, 'label' => $side->label()],
                EyeSide::cases(),
            ),
            'eye_diseases' => EyeDisease::options(),
            'eye_surgeries' => EyeSurgery::options(),
            'systemic_diseases' => SystemicDisease::options(),
            'glasses_usage' => GlassesUsage::options(),
            'contact_lenses' => ContactLensType::options(),
            'iop_methods' => IopMethod::options(),
            'diagnoses' => Diagnosis::active()->orderBy('name')->get(['id', 'name', 'code']),
            'investigations' => Service::active()
                ->whereIn('dept', array_map(
                    fn (Department $dept) => $dept->value,
                    SaveMedicalExaminationRequest::INVESTIGATION_DEPARTMENTS,
                ))
                ->orderBy('dept')
                ->orderBy('name')
                ->get(['id', 'name', 'dept']),
            'doctors' => Doctor::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }
}
