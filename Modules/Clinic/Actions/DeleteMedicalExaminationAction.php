<?php

namespace Modules\Clinic\Actions;

use App\Services\ActivityLogService;
use Modules\Clinic\Models\MedicalExamination;

class DeleteMedicalExaminationAction
{
    public function __construct(private readonly ActivityLogService $activityLog) {}

    /** Delete a draft examination; eyes, diagnoses and investigations cascade. */
    public function execute(MedicalExamination $examination): void
    {
        $fileNo = $examination->booking?->file_no;

        $examination->delete();

        $this->activityLog->log(
            action: 'medical_examination_deleted',
            module: 'clinic',
            recordId: $examination->id,
            description: "حذف مسودة فحص طبي للحجز: {$fileNo}",
        );
    }
}
