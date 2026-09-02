<?php

namespace Modules\Booking\Http\Requests;

use App\Enums\KinshipDegree;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Admin\Enums\SystemModule;
use Modules\Booking\Models\Booking;
use Modules\Booking\Services\ServicePricingService;
use Modules\Booking\States\CompletedElectronicState;
use Modules\Surgery\Services\SurgeryService;

class UpdateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('booking.edit') ?? false;
    }

    /**
     * The booking price is always derived server-side from the selected
     * service's one-eye / both-eyes prices — the client-submitted price is
     * not trusted.
     */
    protected function prepareForValidation(): void
    {
        $price = app(ServicePricingService::class)->priceFor(
            $this->input('service_id'),
            $this->input('eye_side'),
            $this->input('price') !== null ? (float) $this->input('price') : null,
        );

        if ($price !== null) {
            $this->merge(['price' => $price]);
        }
    }

    public function messages(): array
    {
        return [
            'patient_name.required' => 'اسم المريض مطلوب.',
            'patient_name.max' => 'اسم المريض يجب ألا يتجاوز 150 حرفاً.',
            'patient_phone.max' => 'رقم الهاتف يجب ألا يتجاوز 20 رقماً.',
            'patient_age.integer' => 'السن يجب أن يكون رقماً صحيحاً.',
            'patient_age.min' => 'السن يجب أن يكون 0 على الأقل.',
            'patient_age.max' => 'السن يجب ألا يتجاوز 150.',
            'national_id.max' => 'الرقم القومي يجب ألا يتجاوز 20 رقماً.',
            'gender.in' => 'الجنس غير صالح.',
            'kinship_degree.in' => 'درجة القرابة غير صالحة.',
            'dept.required' => 'القسم مطلوب.',
            'dept.in' => 'القسم المحدد غير صالح.',
            'service_id.exists' => 'الخدمة المحددة غير موجودة.',
            'service_id.required_with' => 'يجب تحديد الخدمة عند اختيار شركة تأمين.',
            'service_name.max' => 'اسم الخدمة يجب ألا يتجاوز 200 حرف.',
            'doctor_id.exists' => 'الطبيب المحدد غير موجود.',
            'ins_company_id.exists' => 'شركة التأمين المحددة غير موجودة.',
            'visit_date.required' => 'تاريخ الزيارة مطلوب.',
            'visit_date.date' => 'تاريخ الزيارة غير صالح.',
            'visit_time.date_format' => 'وقت الزيارة يجب أن يكون بصيغة HH:MM.',
            'price.numeric' => 'السعر يجب أن يكون رقماً.',
            'price.min' => 'السعر يجب أن يكون 0 على الأقل.',
            'discount.numeric' => 'الخصم يجب أن يكون رقماً.',
            'discount.min' => 'الخصم يجب أن يكون 0 على الأقل.',
            'ins_amount.numeric' => 'مبلغ التأمين يجب أن يكون رقماً.',
            'ins_amount.min' => 'مبلغ التأمين يجب أن يكون 0 على الأقل.',
            'paid_amount.numeric' => 'المبلغ المدفوع يجب أن يكون رقماً.',
            'paid_amount.min' => 'المبلغ المدفوع يجب أن يكون 0 على الأقل.',
            'pay_method.required' => 'طريقة الدفع مطلوبة.',
            'pay_method.in' => 'طريقة الدفع غير صالحة.',
            'pay_status.required' => 'حالة الدفع مطلوبة.',
            'pay_status.in' => 'حالة الدفع غير صالحة.',
            'status.in' => 'حالة الحجز غير صالحة.',
            'visit_note.max' => 'الملاحظات يجب ألا تتجاوز 2000 حرف.',
            'bed_id.required_if' => 'رقم السرير مطلوب لأقسام العمليات والليزك.',
            'bed_id.exists' => 'السرير المحدد غير موجود.',
            'eye_side.in' => 'جانب العين غير صالح.',
            'analysis_type.max' => 'نوع التحليل يجب ألا يتجاوز 150 حرفاً.',
            'analysis_notes.max' => 'ملاحظات التحليل يجب ألا تتجاوز 500 حرف.',
        ];
    }

    public function rules(): array
    {
        return [
            'patient_name' => ['required', 'string', 'max:150'],
            'patient_phone' => ['nullable', 'string', 'max:20'],
            'patient_age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'in:male,female'],
            'kinship_degree' => ['nullable', Rule::in(array_column(KinshipDegree::cases(), 'value'))],
            'dept' => ['required', Rule::in(SystemModule::enabledDeptValues())],
            'service_id' => ['nullable', 'required_with:ins_company_id', 'exists:services,id'],
            'service_name' => ['nullable', 'string', 'max:200'],
            'doctor_id' => ['nullable', 'exists:doctors,id'],
            'ins_company_id' => ['nullable', 'exists:insurance_companies,id'],
            'visit_date' => ['required', 'date'],
            'visit_time' => ['nullable', 'date_format:H:i'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'ins_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'pay_method' => ['required', 'in:cash,card,transfer,insurance'],
            'pay_status' => ['required', 'in:unpaid,partial,paid'],
            'status' => [
                'nullable',
                'in:waiting,confirmed,in_progress,completed,completed_electronic,cancelled',
                // "مكتمل - إلكتروني" is a system-only status (set by surgery completion) —
                // only accept it here as a no-op resubmission of an already-electronic booking.
                function ($attribute, $value, $fail) {
                    if ($value !== CompletedElectronicState::$name) {
                        return;
                    }

                    $bookingId = $this->route('booking');
                    $current = $bookingId ? Booking::find($bookingId)?->status : null;

                    if (! $current instanceof CompletedElectronicState) {
                        $fail('لا يمكن تعيين هذه الحالة يدوياً.');
                    }
                },
            ],
            'visit_note' => ['nullable', 'string', 'max:2000'],
            'bed_id' => [
                'nullable',
                'required_if:dept,surgery,lasik',
                'exists:or_beds,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $service = app(SurgeryService::class);
                        $bookingId = $this->route('booking');
                        $excludeSurgeryId = null;
                        if ($bookingId) {
                            $excludeSurgeryId = $service->findByBooking($bookingId)?->id;
                        }
                        if (! $service->isBedAvailable($value, $this->visit_date, $excludeSurgeryId)) {
                            $fail('هذا السرير مشغول في التاريخ المحدد أو يوجد به مريض حالياً.');
                        }
                    }
                },
            ],
            'eye_side' => ['nullable', 'in:OD,OS,OU'],
            'analysis_type' => ['nullable', 'string', 'max:150'],
            'analysis_notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
