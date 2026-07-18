<?php

namespace Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Models\JournalEntry;
use Modules\Admin\Enums\SystemModule;

class CostCenterController extends Controller
{
    /** Cost centers tied to a disable-able clinical department. */
    private const DEPT_COST_CENTERS = [
        CostCenter::Clinic->value => SystemModule::Clinic,
        CostCenter::Lab->value => SystemModule::Labs,
        CostCenter::Surgery->value => SystemModule::Surgery,
        CostCenter::Lasik->value => SystemModule::Lasik,
        CostCenter::Laser->value => SystemModule::Laser,
    ];

    public function index(): Response
    {
        $from = request('from');
        $to = request('to');

        $stats = JournalEntry::query()
            ->selectRaw('cost_center, COUNT(*) as entry_count, SUM(amount) as total_amount')
            ->whereNotNull('cost_center')
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->groupBy('cost_center')
            ->get()
            ->keyBy('cost_center');

        $centers = collect(CostCenter::cases())
            ->reject(fn (CostCenter $cc) => isset(self::DEPT_COST_CENTERS[$cc->value])
                && ! self::DEPT_COST_CENTERS[$cc->value]->isEnabled())
            ->map(fn (CostCenter $cc) => [
                'code' => $cc->value,
                'label' => $cc->label(),
                'entry_count' => (int) ($stats->get($cc->value)?->entry_count ?? 0),
                'total_amount' => (float) ($stats->get($cc->value)?->total_amount ?? 0),
            ])
            ->values();

        return Inertia::render('journal/CostCenters', [
            'centers' => $centers,
            'filters' => ['from' => $from, 'to' => $to],
            'grand_total' => $centers->sum('total_amount'),
            'grand_entries' => $centers->sum('entry_count'),
        ]);
    }
}
