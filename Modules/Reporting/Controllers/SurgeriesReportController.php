<?php

namespace Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SurgeriesReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $from = $request->input('from', today()->subDays(30)->toDateString());
        $to = $request->input('to', today()->toDateString());
        $dept = $request->input('dept');

        $rows = DB::table('surgeries')
            ->join('bookings', 'bookings.id', '=', 'surgeries.booking_id')
            ->leftJoin('doctors', 'doctors.id', '=', 'surgeries.surgeon_id')
            ->when($from, fn ($q) => $q->whereDate('surgeries.scheduled_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('surgeries.scheduled_at', '<=', $to))
            ->when($dept, fn ($q) => $q->where('surgeries.dept', $dept))
            ->whereNotIn('bookings.status', ['cancelled'])
            ->select([
                'bookings.file_no',
                'bookings.patient_name',
                'bookings.paid_amount',
                'surgeries.dept',
                'surgeries.procedure',
                'surgeries.eye',
                'surgeries.status',
                'surgeries.scheduled_at',
                'surgeries.supply_total',
                'surgeries.complications',
                DB::raw('doctors.name as surgeon_name'),
            ])
            ->orderByDesc('surgeries.scheduled_at')
            ->get()
            ->map(fn ($r) => [
                'file_no' => $r->file_no,
                'patient_name' => $r->patient_name,
                'paid_amount' => (float) $r->paid_amount,
                'dept' => $r->dept,
                'procedure' => $r->procedure,
                'eye' => $r->eye,
                'status' => $r->status,
                'scheduled_at' => $r->scheduled_at,
                'supply_total' => (float) $r->supply_total,
                'complications' => $r->complications,
                'surgeon_name' => $r->surgeon_name,
            ])
            ->values()
            ->all();

        $rows = collect($rows);
        $surgeryCount = $rows->where('dept', 'surgery')->count();
        $lasikCount = $rows->where('dept', 'lasik')->count();
        $totalSupplies = $rows->sum('supply_total');

        return Inertia::render('reports/SurgeriesReport', [
            'data' => [
                'rows' => $rows->values()->all(),
                'total_count' => $rows->count(),
                'surgery_count' => $surgeryCount,
                'lasik_count' => $lasikCount,
                'total_supplies' => $totalSupplies,
                'from' => $from,
                'to' => $to,
            ],
            'filters' => compact('from', 'to', 'dept'),
        ]);
    }
}
