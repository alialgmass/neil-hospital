<?php

namespace Modules\HR\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\HR\Models\Leave;
use Modules\HR\Services\HRService;

class LeaveController extends Controller
{
    public function __construct(private readonly HRService $hr) {}

    public function index(): Response
    {
        $filters = request()->only(['employee_id', 'type', 'status']);

        $stats = [
            'pending' => Leave::where('status', 'pending')->count(),
            'approved' => Leave::where('status', 'approved')->count(),
            'rejected' => Leave::where('status', 'rejected')->count(),
            'total_days' => (int) Leave::where('status', 'approved')->sum('days'),
        ];

        return Inertia::render('hr/Leaves', [
            'leaves' => $this->hr->listLeaves($filters, 25),
            'employees' => $this->hr->getActiveEmployees(),
            'filters' => $filters,
            'stats' => $stats,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'type' => ['required', 'string'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'gte:from_date'],
            'reason' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->hr->createLeave($data);

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
