<?php

namespace Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Reporting\Exports\TabularReportExport;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReceptionReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        [$from, $to] = $this->range($request);
        $appointments = $this->appointments($from, $to);

        return Inertia::render('reports/Reception', [
            'data' => [
                'rows' => $appointments->all(),
                'stats' => $this->stats($appointments),
                'from' => $from,
                'to' => $to,
            ],
            'filters' => compact('from', 'to'),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        [$from, $to] = $this->range($request);

        $rows = $this->appointments($from, $to)->map(fn (array $r): array => [
            $r['visit_date'], $r['visit_time'], $r['file_no'], $r['patient_name'], $r['patient_phone'],
            $r['dept'], $r['service_name'], $r['doctor_name'], $r['status'], $r['pay_status'],
        ])->all();

        return Excel::download(
            new TabularReportExport(['التاريخ', 'الوقت', 'رقم الملف', 'المريض', 'الهاتف', 'القسم', 'الخدمة', 'الطبيب', 'الحالة', 'السداد'], $rows),
            'reception-report.xlsx',
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function range(Request $request): array
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        return [
            $validated['from'] ?? today()->toDateString(),
            $validated['to'] ?? $validated['from'] ?? today()->toDateString(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function appointments(string $from, string $to): Collection
    {
        return DB::table('bookings')
            ->leftJoin('doctors', 'doctors.id', '=', 'bookings.doctor_id')
            ->whereDate('bookings.visit_date', '>=', $from)
            ->whereDate('bookings.visit_date', '<=', $to)
            ->orderBy('bookings.visit_date')
            ->orderBy('bookings.visit_time')
            ->get([
                'bookings.visit_date', 'bookings.visit_time', 'bookings.file_no', 'bookings.patient_name',
                'bookings.patient_phone', 'bookings.dept', 'bookings.service_name', 'bookings.status',
                'bookings.pay_status', 'doctors.name as doctor_name',
            ])
            ->map(fn ($r): array => (array) $r);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $appointments
     * @return array<string, mixed>
     */
    private function stats(Collection $appointments): array
    {
        $count = fn (string $key) => $appointments->countBy($key)->all();

        return [
            'total' => $appointments->count(),
            'by_status' => $count('status'),
            'by_dept' => $count('dept'),
            'by_pay_status' => $count('pay_status'),
            'by_day' => $appointments->countBy(fn (array $r) => substr((string) $r['visit_date'], 0, 10))->all(),
        ];
    }
}
