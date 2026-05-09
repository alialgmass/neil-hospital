<?php

namespace Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Reporting\Services\ExcelExportService;
use Modules\Reporting\Services\ReportingService;

class DoctorClaimsReportController extends Controller
{
    public function __construct(
        private readonly ReportingService $reportingService,
        private readonly ExcelExportService $excelExportService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $from = $request->input('from', today()->subDays(30)->toDateString());
        $to = $request->input('to', today()->toDateString());
        $doctorId = $request->input('doctor_id');

        return Inertia::render('reports/DoctorClaims', [
            'data' => $this->reportingService->doctorClaims($from, $to, $doctorId),
            'filters' => compact('from', 'to', 'doctorId'),
        ]);
    }

    public function export(Request $request)
    {
        $from = $request->input('from', today()->subDays(30)->toDateString());
        $to = $request->input('to', today()->toDateString());
        $doctorId = $request->input('doctor_id');

        return $this->excelExportService->export('doctor-claims', $this->reportingService->doctorClaims($from, $to, $doctorId));
    }
}
