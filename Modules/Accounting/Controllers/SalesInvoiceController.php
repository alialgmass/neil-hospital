<?php

namespace Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Booking\Models\Booking;
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'booking_id' => 'required|string|exists:bookings,id',
            'amount_paid' => 'required|numeric|min:0',
            'pay_method' => 'required|in:cash,card,transfer,insurance',
            'ins_amount' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
        ]);

        $booking = $this->bookingService->recordPayment($data['booking_id'], $data);

        return redirect("/booking/{$booking->id}/receipt")->with('success', 'تم إصدار الفاتورة بنجاح.');
    }
}
