<?php

namespace Modules\Booking\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Actions\AutoPostBookingPaymentAction;
use Modules\Booking\Models\Booking;

class PayBookingController extends Controller
{
    public function __construct(
        private readonly AutoPostBookingPaymentAction $autoPostAction,
    ) {}

    public function __invoke(Request $request, string $id): RedirectResponse
    {
        $booking = Booking::findOrFail($id);

        $data = $request->validate([
            'paid_amount' => ['required', 'numeric', 'min:0.01'],
            'pay_method' => ['required', 'in:cash,card,transfer,insurance'],
        ]);

        $paymentAmount = (float) $data['paid_amount'];
        $newPaidTotal = (float) $booking->paid_amount + $paymentAmount;
        $netDue = max(0.0, (float) $booking->price - (float) $booking->discount - (float) $booking->ins_amount);

        $payStatus = $newPaidTotal >= $netDue ? 'paid' : 'partial';

        $booking->update([
            'paid_amount' => $newPaidTotal,
            'pay_method' => $data['pay_method'],
            'pay_status' => $payStatus,
        ]);

        $this->autoPostAction->execute($booking->fresh(), $paymentAmount);

        return back()->with('success', "تم تسجيل دفعة {$paymentAmount} ج بنجاح.");
    }
}
