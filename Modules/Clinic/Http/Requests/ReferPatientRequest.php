<?php

namespace Modules\Clinic\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReferPatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('clinic.write') ?? false;
    }

    public function rules(): array
    {
        return [
            'referral_to' => ['required', 'in:labs,surgery,lasik,laser'],
            'create_follow_up' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'referral_to.required' => 'يجب تحديد القسم المُوجَّه إليه.',
            'referral_to.in' => 'القسم المحدد غير صالح.',
        ];
    }
}
