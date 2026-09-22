<?php

namespace Modules\Surgery\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Booking\Models\Booking;
use Modules\Inventory\Models\InventoryItem;
use Modules\Surgery\Actions\ProcessBundleSupplyAction;
use Modules\Surgery\Actions\RecordSuppliesUsedAction;
use Modules\Surgery\Actions\RecordSurgeryReportAction;
use Modules\Surgery\Actions\ScheduleSurgeryAction;
use Modules\Surgery\Actions\UpdateSurgeryStatusAction;
use Modules\Surgery\DTOs\SuppliesUsedData;
use Modules\Surgery\DTOs\SurgeryData;
use Modules\Surgery\Http\Requests\RecordSuppliesRequest;
use Modules\Surgery\Http\Requests\StoreSurgeryRequest;
use Modules\Surgery\Models\Surgery;
use Modules\Surgery\Services\SurgeryService;

class SurgeryController extends Controller
{
    public function __construct(
        private readonly SurgeryService $surgeryService,
        private readonly ScheduleSurgeryAction $scheduleAction,
        private readonly RecordSurgeryReportAction $reportAction,
        private readonly RecordSuppliesUsedAction $suppliesAction,
        private readonly UpdateSurgeryStatusAction $statusAction,
        private readonly ProcessBundleSupplyAction $bundleAction,
    ) {}

    public function index(): Response
    {
        $dept = request()->segment(1, 'surgery');
        $status = request('status');
        $today = today()->toDateString();

        $page = match ($dept) {
            'lasik' => 'lasik/Index',
            'laser' => 'laser/Index',
            default => 'surgery/Index',
        };

        $bookings = $this->surgeryService->getUnscheduledBookings($dept);
        $prefill = $this->resolveTransferPrefill($dept, $bookings);

        return Inertia::render($page, [
            'surgeries' => $this->surgeryService->list($dept, $status, 200),
            'bookings' => $bookings,
            'orRooms' => $this->surgeryService->getOrRoomsWithBedStatus($dept, $today),
            'bundles' => $this->surgeryService->getActiveBundles($dept),
            'doctors' => $this->surgeryService->getActiveDoctors(),
            'dept' => $dept,
            'filters' => ['status' => $status],
            'revenue' => $this->surgeryService->getTodayRevenue($dept),
            'prefill' => $prefill,
        ]);
    }

    /**
     * When arriving from the medical-record "transfer" action
     * (?transfer_booking_id=...), authorize it server-side (not just a
     * hidden button), make sure the referenced booking is still available
     * to schedule for $bookings (it may not share $dept, e.g. a Clinic
     * visit transferred to Surgery), and refuse to prefill anything if a
     * Surgery already exists for that booking — prevents a duplicate
     * Operation on refresh/back.
     *
     * @param  Collection  $bookings  the $dept-scoped unscheduled bookings list; mutated in
     *                                place (via prepend, an object-mutating Collection method)
     *                                to include the transferred booking when it's from another dept
     */
    private function resolveTransferPrefill(string $dept, Collection $bookings): ?array
    {
        $transferBookingId = request()->query('transfer_booking_id');

        if (! $transferBookingId) {
            return null;
        }

        abort_unless(Auth::user()?->can('transfer_medical_record'), 403, 'ليس لديك صلاحية التحويل من الملف الطبي.');

        if (Surgery::where('booking_id', $transferBookingId)->exists()) {
            // Already converted — don't prefill a form that would duplicate it.
            return null;
        }

        $booking = Booking::select('id', 'file_no', 'patient_name')->find($transferBookingId);

        if (! $booking) {
            return null;
        }

        if (! $bookings->contains('id', $booking->id)) {
            $bookings->prepend($booking);
        }

        return [
            'booking_id' => $booking->id,
            'dept' => $dept,
            'eye' => request()->query('eye'),
            'service_id' => request()->query('service_id'),
        ];
    }

    /**
     * Live search for individual supply/item selection in the surgery
     * "consumed supplies" form — avoids loading the full inventory at once.
     */
    public function searchItems(): JsonResponse
    {
        $term = trim((string) request()->query('q', ''));

        if ($term === '') {
            return response()->json([]);
        }

        $items = InventoryItem::search($term)
            ->where('quantity', '>', 0)
            ->select('id', 'name', 'code', 'sell_price', 'quantity')
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json($items);
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

    /**
     * Bulk-record supplies (individual items + bundles) for one case.
     * All-or-nothing: bundle stock permits / journal entries and the
     * supplies_used update roll back together if anything fails.
     */
    public function supplies(RecordSuppliesRequest $request, string $id): RedirectResponse
    {
        $dept = $request->dept()->value;
        $validated = $request->validated();

        $surgery = DB::transaction(function () use ($validated, $dept, $id) {
            $allItems = $validated['items'] ?? [];

            foreach ($validated['bundles'] ?? [] as $bundleReq) {
                $allItems[] = $this->bundleAction->process(
                    $bundleReq['bundle_id'],
                    max(1, (int) ($bundleReq['qty'] ?? 1)),
                    $dept,
                    $bundleReq['selected_items'] ?? [],
                    $id,
                );
            }

            return $this->suppliesAction->execute(SuppliesUsedData::fromArray([
                'surgery_id' => $id,
                'items' => $allItems,
            ]));
        });

        session()->flash('surgery.supplies_used', $surgery->supplies_used);
        session()->flash('surgery.supply_total', $surgery->supply_total);

        return back()->with('success', 'تم تسجيل المستلزمات المستخدمة.');
    }

    public function updateStatus(string $id): RedirectResponse
    {
        request()->validate([
            'status' => 'required|in:scheduled,prep,in_progress,completed,cancelled',
        ]);

        $this->statusAction->execute($id, request('status'));

        return back()->with('success', 'تم تحديث حالة الإجراء.');
    }
}
