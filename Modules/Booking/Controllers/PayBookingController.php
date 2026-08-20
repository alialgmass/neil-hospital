<?php

namespace Modules\Booking\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Actions\AutoPostBookingPaymentAction;
use Modules\Accounting\Actions\AutoPostDoctorDuesAction;
use Modules\Booking\Models\Booking;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Services\DoctorClaimsService;

class PayBookingController extends Controller
{
    public function __construct(
        private readonly AutoPostBookingPaymentAction $autoPostAction,
        private readonly AutoPostDoctorDuesAction $autoPostDoctorDues,
        private readonly DoctorClaimsService $doctorClaimsService,
    ) {}

    public function __invoke(Request $request, string $id): RedirectResponse
    {
        $booking = Booking::findOrFail($id);

        $data = $request->validate([
            'paid_amount' => ['required', 'numeric', 'min:0.01'],
            'pay_method' => ['required', 'in:cash,card,transfer,insurance'],
        ], [
            'price.required' => 'سعر الحجز مطلوب.',
            'price.numeric' => 'سعر الحجز يجب أن يكون رقماً.',
            'price.min' => 'سعر الحجز يجب أن يكون 0 على الأقل.',
            'paid_amount.required' => 'المبلغ المدفوع مطلوب.',
            'paid_amount.numeric' => 'المبلغ المدفوع يجب أن يكون رقماً.',
            'paid_amount.min' => 'المبلغ المدفوع يجب أن يكون 0.01 على الأقل.',
            'pay_method.required' => 'طريقة الدفع مطلوبة.',
            'pay_method.in' => 'طريقة الدفع غير صالحة.',
        ]);

        $isFirstPayment = (float) $booking->paid_amount === 0.0;
        $paymentAmount = (float) $data['paid_amount'];
        $newPaidTotal = (float) $booking->paid_amount + $paymentAmount;
        $newPrice = (float) $data['paid_amount'];
        $netDue = max(0.0, $newPrice - (float) $booking->discount - (float) $booking->ins_amount);

        $payStatus = $newPaidTotal >= $netDue ? 'paid' : 'partial';

        $booking->update([
            'price' => $newPrice,
            'paid_amount' => $newPaidTotal,
            'pay_method' => $data['pay_method'],
            'pay_status' => $payStatus,
        ]);

        $booking = $booking->fresh();

        $this->autoPostAction->execute($booking, $paymentAmount);

        if ($booking->doctor_id) {
            $doctor = Doctor::find($booking->doctor_id);

            if ($doctor) {
                $drShare = $this->doctorClaimsService->computeShareForPayment($doctor, $booking, $paymentAmount, $isFirstPayment);

                if ($drShare > 0) {
                    $this->autoPostDoctorDues->execute(
                        dept: $booking->dept,
                        amount: $drShare,
                        doctorName: $doctor->name,
                        reference: $booking->file_no,
                        date: $booking->visit_date->toDateString(),
                        idempotencyKey: "doctor_dues:{$booking->file_no}:{$newPaidTotal}",
                    );
                }
            }
        }

        return back()->with('success', "تم تسجيل دفعة {$paymentAmount} ج بنجاح.");
    }
}
