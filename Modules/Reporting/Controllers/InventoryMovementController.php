<?php

namespace Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Reporting\Services\ReportingService;

class InventoryMovementController extends Controller
{
    public function __construct(private readonly ReportingService $reportingService) {}

    public function __invoke(Request $request): Response
    {
        $from = $request->input('from', today()->subDays(30)->toDateString());
        $to = $request->input('to', today()->toDateString());
        $itemId = $request->input('item_id');

        return Inertia::render('reports/InventoryMovement', [
            'data' => $this->reportingService->inventoryMovement($from, $to, $itemId),
            'filters' => compact('from', 'to', 'itemId'),
        ]);
    }
}
