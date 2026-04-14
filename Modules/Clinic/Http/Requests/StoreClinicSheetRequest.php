<?php

namespace Modules\Clinic\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClinicSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('clinic.write') ?? false;
    }

    public function rules(): array
    {
        return [
            'booking_id'        => ['required', 'exists:bookings,id'],
            'doctor_id'         => ['nullable', 'exists:doctors,id'],
            'chief_complaint'   => ['nullable', 'string', 'max:2000'],
            'visual_acuity_od'  => ['nullable', 'string', 'max:20'],
            'visual_acuity_os'  => ['nullable', 'string', 'max:20'],
            'iop_od'            => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'iop_os'            => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'anterior_segment'  => ['nullable', 'string', 'max:5000'],
            'posterior_segment' => ['nullable', 'string', 'max:5000'],
            'diagnosis'         => ['nullable', 'string', 'max:5000'],
            'plan'              => ['nullable', 'string', 'max:5000'],
            'referral_to'       => ['nullable', 'in:clinic,labs,surgery,lasik,laser'],
            'notes'             => ['nullable', 'string', 'max:2000'],
        ];
    }
}
