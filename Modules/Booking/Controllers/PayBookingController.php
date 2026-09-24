<?php

namespace Modules\Booking\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Actions\AutoPostBookingPaymentAction;
use Modules\Accounting\Actions\AutoPostDevelopmentFeeAction;
use Modules\Accounting\Actions\AutoPostDoctorDuesAction;
use Modules\Booking\Enums\PayMethod;
use Modules\Booking\Enums\PayStatus;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Doctor\Actions\PostDelegatedDoctorDuesForPaymentAction;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Services\DoctorClaimsService;

class PayBookingController extends Controller
{
    public function __construct(
        private readonly AutoPostBookingPaymentAction $autoPostAction,
        private readonly AutoPostDevelopmentFeeAction $autoPostDevelopmentFee,
        private readonly AutoPostDoctorDuesAction $autoPostDoctorDues,
        private readonly DoctorClaimsService $doctorClaimsService,
        private readonly PostDelegatedDoctorDuesForPaymentAction $postDelegatedDues,
    ) {}

    public function __invoke(Request $request, string $id): RedirectResponse
    {
        $booking = Booking::findOrFail($id);

        $data = $request->validate([
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'pay_method' => ['required', 'in:cash,card,transfer,insurance,contract'],
        ], [
            'price.required' => 'سعر الحجز مطلوب.',
            'price.numeric' => 'سعر الحجز يجب أن يكون رقماً.',
            'price.min' => 'سعر الحجز يجب أن يكون 0 على الأقل.',
            'paid_amount.required' => 'المبلغ المدفوع مطلوب.',
            'paid_amount.numeric' => 'المبلغ المدفوع يجب أن يكون رقماً.',
            'paid_amount.min' => 'المبلغ المدفوع يجب أن يكون 0 على الأقل.',
            'pay_method.required' => 'طريقة الدفع مطلوبة.',
            'pay_method.in' => 'طريقة الدفع غير صالحة.',
        ]);

        // Paying 0: the patient isn't collecting anything on this booking —
        // instead of leaving it unpaid forever, the hospital's expected
        // dues transfer to the doctor as debt (mirrors
        // CreateBookingAction::recordDoctorDebtIfUnpaid, triggered
        // explicitly here instead of only at booking creation).
        if ((float) $data['paid_amount'] === 0.0) {
            return $this->writeOffAsDoctorDebt($booking, $data['pay_method']);
        }

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
        $this->autoPostDevelopmentFee->execute($booking);

        if ($booking->doctor_id) {
            $doctor = Doctor::find($booking->doctor_id);

            if ($doctor) {
                $drShare = $this->doctorClaimsService->computeShareForPayment($doctor, $booking, $paymentAmount, $isFirstPayment);

                // Any outstanding debt on the doctor (from prior unpaid
                // bookings — see CreateBookingAction::recordDoctorDebtIfUnpaid)
                // is settled out of this share before it's posted as dues.
                // Logged per booking (DoctorDebtSettlement) so the claims
                // report can net it out of "مستحق" instead of double-counting it.
                $settled = $drShare > 0 ? $doctor->settleDebtForBooking($booking->id, $drShare) : 0.0;
                $netShare = $drShare - $settled;

                if ($netShare > 0) {
                    $this->autoPostDoctorDues->execute(
                        dept: $booking->dept,
                        amount: $netShare,
                        doctorName: $doctor->name,
                        reference: $booking->file_no,
                        date: $booking->visit_date->toDateString(),
                        idempotencyKey: "doctor_dues:{$booking->file_no}:{$newPaidTotal}",
                    );
                }
            }
        }

        $this->postDelegatedDues->execute($booking, $isFirstPayment, $newPaidTotal);

        return back()->with('success', "تم تسجيل دفعة {$paymentAmount} ج بنجاح.");
    }

    /**
     * Explicitly record that nothing will be collected on this booking: the
     * booking closes out (pay_status → paid, nothing owed by the patient
     * anymore) and, for direct-pay methods, whatever the hospital was
     * expecting to collect (net of discount/insurance already applied and
     * the dev-treasury fee) becomes debt on the doctor instead — same rule
     * as an unpaid cash booking at creation, just triggered from the pay
     * screen. Third-party bookings (insurance/contract) never incur this
     * debt: their doctor fee already accrues independently through
     * SyncDoctorEntitlementAction regardless of patient payment.
     *
     * Guarded to unpaid bookings only, so resubmitting a 0 payment on an
     * already-closed booking can never incur the same debt twice.
     */
    private function writeOffAsDoctorDebt(Booking $booking, string $payMethod): RedirectResponse
    {
        if ($booking->pay_status !== PayStatus::Unpaid) {
            return back()->with('error', 'لا يمكن تسجيل دفعة صفر — الحجز ليس في حالة غير مسدد.');
        }

        $booking->update([
            'pay_method' => $payMethod,
            'pay_status' => PayStatus::Paid,
        ]);

        if ($booking->doctor_id && ! PayMethod::from($payMethod)->isThirdParty()) {
            $doctor = Doctor::find($booking->doctor_id);

            if ($doctor) {
                $outstanding = max(0.0, (float) $booking->price - (float) $booking->discount - (float) $booking->ins_amount - (float) $booking->paid_amount);
                $devFee = $booking->service_id
                    ? (float) (Service::whereKey($booking->service_id)->value('dev_treasury_fee') ?? 0)
                    : 0.0;
                $debt = max(0.0, round($outstanding - $devFee, 2));

                if ($debt > 0) {
                    $doctor->incurDebt($debt);
                }
            }
        }

        return back()->with('success', 'تم تسجيل الحجز كغير محصَّل — أصبح المبلغ ديناً على الطبيب.');
    }
}
