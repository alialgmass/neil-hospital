<?php

namespace Modules\Clinic\Http\Requests;

use App\Enums\EyeSide;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Booking\Models\Service;

class ReferPatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('clinic.write') ?? false;
    }

    public function rules(): array
    {
        return [
            'referral_to' => ['required', 'in:labs,surgery,lasik,laser,pentacam'],
            'create_follow_up' => ['nullable', 'boolean'],
            'service_id' => [
                'nullable',
                'exists:services,id',
                function (string $attribute, mixed $value, callable $fail): void {
                    if ($value && Service::whereKey($value)->where('dept', $this->input('referral_to'))->doesntExist()) {
                        $fail('الخدمة المحددة لا تنتمي إلى القسم المُوجَّه إليه.');
                    }
                },
            ],
            // Operations are priced per eye (one-eye vs both-eyes price), so
            // the side is mandatory as soon as an operation service is chosen.
            'eye_side' => [
                Rule::requiredIf(fn (): bool => $this->filled('service_id')
                    && in_array($this->input('referral_to'), ['surgery', 'lasik', 'laser'], true)),
                'nullable',
                Rule::in(array_column(EyeSide::cases(), 'value')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'referral_to.required' => 'يجب تحديد القسم المُوجَّه إليه.',
            'referral_to.in' => 'القسم المحدد غير صالح.',
            'service_id.exists' => 'الخدمة المحددة غير موجودة.',
            'eye_side.in' => 'جانب العين غير صالح.',
            'eye_side.required' => 'يجب تحديد العين عند اختيار خدمة جراحية.',
        ];
    }
}
