<?php

namespace Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Models\InventoryItem;
use Modules\Reporting\Services\ReportingService;

class InventoryMovementController extends Controller
{
    /** Movement sources the report can be filtered by (see ReportingService::inventoryMovement()). */
    private const SOURCES = ['purchase', 'stock_in', 'issue', 'bundle', 'sale', 'stock_take'];

    public function __construct(private readonly ReportingService $reportingService) {}

    /**
     * period=day   → a single `date` (defaults to today)
     * period=month → a whole `month` (YYYY-MM, defaults to this month)
     * period=range → `from` … `to` (defaults to the last 30 days)
     */
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'period' => 'nullable|in:day,month,range',
            'date' => 'nullable|date',
            'month' => 'nullable|date_format:Y-m',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'item_id' => 'nullable|string|exists:inventory,id',
            'source' => 'nullable|in:'.implode(',', self::SOURCES),
        ]);

        $period = $validated['period'] ?? (isset($validated['from']) || isset($validated['to']) ? 'range' : 'day');

        [$from, $to] = match ($period) {
            'day' => array_fill(0, 2, Carbon::parse($validated['date'] ?? today())->toDateString()),
            'month' => (function () use ($validated) {
                $month = Carbon::createFromFormat('Y-m', $validated['month'] ?? today()->format('Y-m'))->startOfMonth();

                return [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()];
            })(),
            default => [
                $validated['from'] ?? today()->subDays(30)->toDateString(),
                $validated['to'] ?? today()->toDateString(),
            ],
        };

        $itemId = $validated['item_id'] ?? null;
        $source = $validated['source'] ?? null;

        return Inertia::render('reports/InventoryMovement', [
            'data' => $this->reportingService->inventoryMovement($from, $to, $itemId, $source),
            'filters' => [
                'period' => $period,
                'date' => $validated['date'] ?? ($period === 'day' ? $from : today()->toDateString()),
                'month' => $validated['month'] ?? substr($from, 0, 7),
                'from' => $from,
                'to' => $to,
                'item_id' => $itemId,
                'source' => $source,
            ],
            'items' => InventoryItem::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
