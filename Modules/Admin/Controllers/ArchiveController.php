<?php

namespace Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Booking\DTOs\BookingData;
use Modules\Booking\Enums\PayStatus;
use Modules\Booking\Services\BookingService;
use Modules\Doctor\Models\Doctor;

class ArchiveController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {}

    public function index(): Response
    {
        $filters = request()->only(['search', 'dept', 'from', 'to']);

        return Inertia::render('admin/Archive', [
            'bookings' => $this->bookingService->getArchive($filters, 30),
            'filters' => $filters,
            'doctors' => Doctor::select('id', 'name')->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'patient_name' => ['required', 'string', 'max:200'],
            'patient_phone' => ['nullable', 'string', 'max:30'],
            'patient_age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'gender' => ['nullable', 'in:male,female'],
            'dept' => ['required', 'in:clinic,labs,surgery,lasik,laser'],
            'doctor_id' => ['nullable', 'exists:doctors,id'],
            'visit_date' => ['required', 'date'],
            'service_name' => ['nullable', 'string', 'max:200'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'pay_method' => ['nullable', 'in:cash,card,transfer,insurance'],
            'visit_note' => ['nullable', 'string'],
        ]);

        $price = (float) ($data['price'] ?? 0);
        $paid = (float) ($data['paid_amount'] ?? 0);
        $payStatus = $paid >= $price && $price > 0
            ? PayStatus::Paid
            : ($paid > 0 ? PayStatus::Partial : PayStatus::Unpaid);

        $bookingData = BookingData::fromArray([
            ...$data,
            'price' => $price,
            'paid_amount' => $paid,
            'pay_method' => $data['pay_method'] ?? 'cash',
            'pay_status' => $payStatus->value,
            'status' => 'completed',
        ]);

        $this->bookingService->create($bookingData, auth()->id());

        return redirect()->route('archive')->with('success', 'تم إضافة السجل إلى الأرشيف بنجاح.');
    }
}
