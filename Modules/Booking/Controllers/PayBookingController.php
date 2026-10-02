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

        // The agreed price is a commercial term, fixed when the booking is
        // created/edited. It must not be rewritten by a payment: doing so
        // inflated the price on every partial payment, and — because netDue
        // used to be derived from the payment itself — compared 3,000 >= 3,000
        // and marked a 10,000 booking fully paid.
        //
        // A booking saved without a price (0) is the one legitimate exception:
        // the amount collected establishes it. Prefer the linked service's
        // price as the authority when the row itself carries none, so a
        // multi-instalment collection still accumulates against the real
        // figure instead of being capped at the first instalment.
        $bookingPrice = (float) $booking->price;
        $servicePrice = (float) ($booking->service?->price ?? 0);
        $agreedPrice = $bookingPrice > 0 ? $bookingPrice : $servicePrice;
        $isUnpriced = $agreedPrice <= 0;
        $paymentAmount = (float) $request->input('paid_amount', 0);
        $effectivePrice = $isUnpriced ? $paymentAmount : $agreedPrice;

        // What the patient still owes. 0 is always allowed — it is the
        // write-off signal handled below — so the cap only bites on real money.
        $netDue = max(0.0, $effectivePrice - (float) $booking->discount - (float) $booking->ins_amount);
        $remaining = max(0.0, $netDue - (float) $booking->paid_amount);

        $data = $request->validate([
            'paid_amount' => [
                'required',
                'numeric',
                'min:0',
                function (string $attribute, mixed $value, \Closure $fail) use ($remaining, $isUnpriced): void {
                    if (! $isUnpriced && (float) $value > 0 && (float) $value > $remaining) {
                        $fail("المبلغ المدفوع أكبر من المتبقي على الحجز ({$remaining}).");
                    }
                },
            ],
            'pay_method' => ['required', 'in:cash,card,transfer,insurance,contract'],
        ], [
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
        $newPaidTotal = (float) $booking->paid_amount + $paymentAmount;

        $payStatus = $newPaidTotal >= $netDue ? PayStatus::Paid : PayStatus::Partial;

        $booking->update([
            // Persist the price only when the row was missing one — the
            // service figure when there is one, otherwise what was collected.
            ...($bookingPrice > 0 ? [] : ['price' => $isUnpriced ? $paymentAmount : $agreedPrice]),
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

                // Once the booking is collected in full, the debt that was
                // booked against this doctor *for this very booking* no longer
                // describes a loss — the money arrived. Release it rather
                // than netting it off the share, which used to leave a
                // fully-paid case still showing a "خصم مديونية الطبيب" line.
                $released = $payStatus === PayStatus::Paid
                    ? $doctor->releaseDebtForBooking($booking->id)
                    : 0.0;

                // Debt raised by *other* still-unpaid cases is different: it is
                // a real outstanding liability, so it is settled out of this
                // share before the rest is posted as dues. Logged per booking
                // (DoctorDebtSettlement) so the claims report can net it out of
                // "مستحق" instead of double-counting it.
                $settleable = max(0.0, $drShare - $released);
                $settled = $settleable > 0 ? $doctor->settleDebtForBooking($booking->id, $settleable) : 0.0;
                $netShare = $drShare - $released - $settled;

                if ($netShare > 0) {
                    $this->autoPostDoctorDues->execute(
                        dept: $booking->dept,
                        amount: $netShare,
                        doctorId: $doctor->id,
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
     * Works from Unpaid or Partial — whatever is still outstanding is what
     * becomes debt. Guarded against an already-closed (Paid) booking, so
     * resubmitting a 0 payment can never incur the same debt twice.
     */
    private function writeOffAsDoctorDebt(Booking $booking, string $payMethod): RedirectResponse
    {
        if ($booking->pay_status === PayStatus::Paid) {
            return back()->with('error', 'لا يمكن تسجيل دفعة صفر — الحجز مسدد بالفعل.');
        }

        $booking->update([
            'pay_method' => $payMethod,
            'pay_status' => PayStatus::Paid,
        ]);

        if ($booking->doctor_id && ! PayMethod::from($payMethod)->isThirdParty()) {
            $doctor = Doctor::find($booking->doctor_id);

            if ($doctor) {
                // Department-specific rule, shared with CreateBookingAction so a
                // booking can never incur two different debts depending on
                // which screen closed it out.
                $debt = $this->doctorClaimsService->debtForZeroPayment($booking);

                if ($debt > 0) {
                    $doctor->incurDebtForBooking($booking->id, $debt);
                }
            }
        }

        return back()->with('success', 'تم تسجيل الحجز كغير محصَّل — أصبح المبلغ ديناً على الطبيب.');
    }
}
