<?php

namespace Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\InsuranceCompany;
use Modules\Booking\Services\BookingService;

class SalesInvoiceController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService
    ) {}

    public function show(string $bookingId): Response
    {
        $booking = Booking::with(['doctor', 'creator'])->findOrFail($bookingId);

        return Inertia::render('accounting/SalesInvoice', [
            'booking' => $booking,
            'insuranceCompanies' => InsuranceCompany::select('id', 'name', 'coverage_pct')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'booking_id' => 'required|string|exists:bookings,id',
            'amount_paid' => 'required|numeric|min:0',
            'pay_method' => 'required|in:cash,card,transfer,insurance',
            'ins_company_id' => 'nullable|required_if:pay_method,insurance|string|exists:insurance_companies,id',
            'ins_amount' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
        ], [
            'ins_company_id.required_if' => 'يجب اختيار شركة التأمين.',
        ]);

        $data['created_by'] = $request->user()->id;

        $booking = $this->bookingService->recordPayment($data['booking_id'], $data);

        return redirect()->route('booking.receipt', $booking->id)->with('success', 'تم إصدار الفاتورة بنجاح.');
    }
}
