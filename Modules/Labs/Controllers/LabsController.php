<?php

namespace Modules\Labs\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Clinic\Services\MedicalExaminationService;
use Modules\Labs\Models\DiagnosticResult;
use Modules\Labs\Services\LabsService;

class LabsController extends Controller
{
    public function __construct(
        private readonly LabsService $labsService,
        private readonly MedicalExaminationService $examinationService,
    ) {}

    public function index(): Response
    {
        $date = request('date', today()->toDateString());
        $search = request('search');

        return Inertia::render('labs/Index', [
            'queue' => $this->labsService->getQueue($date, $search),
            'date' => $date,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Result letter for a recorded result. The visit's medical examination is
     * now where findings are recorded, so when one exists its findings are
     * shown in the letter too (for users allowed to view examinations).
     */
    public function referralLetter(Request $request, string $resultId): Response
    {
        $result = DiagnosticResult::with(['booking:id,file_no,patient_name', 'technician:id,name'])
            ->findOrFail($resultId);

        $examination = $request->user()->can('examinations.view')
            ? $result->booking?->medicalExamination
            : null;

        return Inertia::render('labs/ReferralLetter', [
            'result' => $result,
            'examination' => $examination ? $this->examinationService->loadForDisplay($examination) : null,
            'options' => $examination ? $this->examinationService->formOptions() : null,
        ]);
    }
}
