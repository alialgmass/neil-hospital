<?php

namespace Modules\Surgery\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Booking\Models\Booking;
use Modules\Doctor\Models\Doctor;
use Modules\Inventory\Models\InventoryItem;
use Modules\Surgery\Actions\RecordSuppliesUsedAction;
use Modules\Surgery\Actions\RecordSurgeryReportAction;
use Modules\Surgery\Actions\ScheduleSurgeryAction;
use Modules\Surgery\DTOs\SuppliesUsedData;
use Modules\Surgery\DTOs\SurgeryData;
use Modules\Surgery\Http\Requests\RecordSuppliesRequest;
use Modules\Surgery\Http\Requests\StoreSurgeryRequest;
use Modules\Surgery\Models\OrRoom;
use Modules\Surgery\Services\SurgeryService;

class SurgeryController extends Controller
{
    public function __construct(
        private readonly SurgeryService $surgeryService,
        private readonly ScheduleSurgeryAction $scheduleAction,
        private readonly RecordSurgeryReportAction $reportAction,
        private readonly RecordSuppliesUsedAction $suppliesAction,
    ) {}

    public function index(): Response
    {
        $dept = request()->segment(1, 'surgery');
        $status = request('status');

        $page = match ($dept) {
            'lasik' => 'lasik/Index',
            'laser' => 'laser/Index',
            default => 'surgery/Index',
        };

        $surgeries = $this->surgeryService->list($dept, $status, 200);

        $bookings = Booking::where('dept', $dept)
            ->whereIn('status', ['waiting', 'confirmed'])
            ->select('id', 'file_no', 'patient_name')
            ->orderByDesc('visit_date')
            ->get();

        $orRooms = OrRoom::with(['beds' => function ($q) use ($dept) {
            $q->orderBy('bed_number')
                ->with(['surgery' => function ($sq) use ($dept) {
                    $sq->where('dept', $dept)->with(['booking', 'surgeon']);
                }]);
        }])->orderBy('name')->get();

        $inventoryItems = InventoryItem::select('id', 'name', 'code', 'sell_price', 'quantity')
            ->where('quantity', '>', 0)
            ->orderBy('name')
            ->get();

        return Inertia::render($page, [
            'surgeries' => $surgeries,
            'orRooms' => $orRooms,
            'inventoryItems' => $inventoryItems,
            'doctors' => Doctor::select('id', 'name')->orderBy('name')->get(),
            'bookings' => $bookings,
            'dept' => $dept,
            'filters' => ['status' => $status],
        ]);
    }

    public function store(StoreSurgeryRequest $request): RedirectResponse
    {
        $data = SurgeryData::fromArray($request->validated());
        $this->scheduleAction->execute($data);

        return back()->with('success', 'تم جدولة الإجراء بنجاح.');
    }

    public function report(string $id): RedirectResponse
    {
        $this->reportAction->execute($id, request()->only(['op_report', 'post_op_notes', 'complications']));

        return back()->with('success', 'تم تسجيل تقرير العملية.');
    }

    public function supplies(RecordSuppliesRequest $request): RedirectResponse
    {
        $data = SuppliesUsedData::fromArray($request->validated());
        $this->suppliesAction->execute($data);

        return back()->with('success', 'تم تسجيل المستلزمات المستخدمة.');
    }
}
