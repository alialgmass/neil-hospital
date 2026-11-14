<?php

namespace Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Doctor\Models\Doctor;
use Modules\Reporting\Exports\TabularReportExport;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DoctorCasesReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        [$from, $to, $doctorId] = $this->filters($request);

        return Inertia::render('reports/DoctorCases', [
            'data' => $this->report($from, $to, $doctorId),
            'filters' => compact('from', 'to', 'doctorId'),
            'doctors' => Doctor::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        [$from, $to, $doctorId] = $this->filters($request);

        $rows = [];

        foreach ($this->report($from, $to, $doctorId)['doctors'] as $doctor) {
            foreach ($doctor['cases'] as $case) {
                $rows[] = [
                    $doctor['doctor_name'], $case['visit_date'], $case['file_no'], $case['patient_name'],
                    $case['service_name'], $case['net_due'], $case['paid'], $case['remaining'],
                ];
            }
        }

        return Excel::download(
            new TabularReportExport(['الطبيب', 'التاريخ', 'رقم الملف', 'اسم الحالة', 'الخدمة', 'المستحق', 'المدفوع', 'المتبقي'], $rows),
            'doctor-cases.xlsx',
        );
    }

    /**
     * @return array{0: string, 1: string, 2: ?string}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'doctor_id' => 'nullable|string',
        ]);

        return [
            $validated['from'] ?? today()->subDays(30)->toDateString(),
            $validated['to'] ?? today()->toDateString(),
            $validated['doctor_id'] ?? null,
        ];
    }

    /**
     * Cases per doctor with what was due, paid and is still outstanding.
     *
     * @return array{doctors: list<array<string, mixed>>, totals: array<string, float|int>, from: string, to: string}
     */
    private function report(string $from, string $to, ?string $doctorId): array
    {
        $cases = DB::table('bookings')
            ->join('doctors', 'doctors.id', '=', 'bookings.doctor_id')
            ->whereDate('bookings.visit_date', '>=', $from)
            ->whereDate('bookings.visit_date', '<=', $to)
            ->where('bookings.status', '!=', 'cancelled')
            ->when($doctorId, fn ($q, $v) => $q->where('bookings.doctor_id', $v))
            ->orderBy('doctors.name')
            ->orderBy('bookings.visit_date')
            ->get([
                'doctors.id as doctor_id', 'doctors.name as doctor_name', 'bookings.visit_date', 'bookings.file_no',
                'bookings.patient_name', 'bookings.service_name', 'bookings.price', 'bookings.discount',
                'bookings.ins_amount', 'bookings.paid_amount',
            ])
            ->map(function ($row): array {
                $netDue = max(0.0, (float) $row->price - (float) $row->discount - (float) $row->ins_amount);
                $paid = (float) $row->paid_amount;

                return [
                    'doctor_id' => $row->doctor_id,
                    'doctor_name' => $row->doctor_name,
                    'visit_date' => $row->visit_date,
                    'file_no' => $row->file_no,
                    'patient_name' => $row->patient_name,
                    'service_name' => $row->service_name,
                    'net_due' => $netDue,
                    'paid' => $paid,
                    'remaining' => max(0.0, $netDue - $paid),
                ];
            });

        $doctors = $cases->groupBy('doctor_id')->map(fn ($group): array => [
            'doctor_id' => $group->first()['doctor_id'],
            'doctor_name' => $group->first()['doctor_name'],
            'cases' => $group->values()->all(),
            'cases_count' => $group->count(),
            'net_due' => $group->sum('net_due'),
            'paid' => $group->sum('paid'),
            'remaining' => $group->sum('remaining'),
        ])->values()->all();

        return [
            'doctors' => $doctors,
            'totals' => [
                'cases_count' => $cases->count(),
                'net_due' => $cases->sum('net_due'),
                'paid' => $cases->sum('paid'),
                'remaining' => $cases->sum('remaining'),
            ],
            'from' => $from,
            'to' => $to,
        ];
    }
}
