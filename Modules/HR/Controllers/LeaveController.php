<?php

namespace Modules\HR\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\HR\Enums\LeaveStatus;
use Modules\HR\Models\Leave;
use Modules\HR\Requests\StoreLeaveRequest;
use Modules\HR\Services\HRService;

class LeaveController extends Controller
{
    public function __construct(private readonly HRService $hr) {}

    public function index(): Response
    {
        $filters = request()->only(['employee_id', 'type', 'status']);

        $stats = [
            'pending' => Leave::where('status', LeaveStatus::Pending)->count(),
            'approved' => Leave::where('status', LeaveStatus::Approved)->count(),
            'rejected' => Leave::where('status', LeaveStatus::Rejected)->count(),
            'total_days' => (int) Leave::where('status', LeaveStatus::Approved)->sum('days'),
        ];

        return Inertia::render('hr/Leaves', [
            'leaves' => $this->hr->listLeaves($filters, 25),
            'employees' => $this->hr->getActiveEmployees(),
            'filters' => $filters,
            'stats' => $stats,
        ]);
    }

    public function store(StoreLeaveRequest $request): RedirectResponse
    {
        $this->hr->createLeave($request->validated());

        return back()->with('success', 'تم تسجيل الإجازة بنجاح.');
    }

    public function approve(string $id): RedirectResponse
    {
        $this->hr->approveLeave($id);

        return back()->with('success', 'تمت الموافقة على الإجازة.');
    }

    public function reject(string $id): RedirectResponse
    {
        $this->hr->rejectLeave($id);

        return back()->with('success', 'تم رفض الإجازة.');
    }
}
