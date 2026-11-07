<?php

namespace Modules\Surgery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSurgeryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $dept = $this->input('dept', 'surgery');

        return $this->user()?->can("{$dept}.write") ?? false;
    }

    public function rules(): array
    {
        return [
            'booking_id' => ['required', 'exists:bookings,id'],
            'dept' => ['required', 'in:surgery,lasik,laser'],
            'or_bed_id' => ['nullable', 'exists:or_beds,id'],
            'bed_no' => ['nullable', 'integer', 'min:1', 'max:999'],
            'surgeon_id' => ['nullable', 'exists:doctors,id'],
            'eye' => ['nullable', 'in:OD,OS,OU'],
            'procedure' => ['nullable', 'string', 'max:300'],
            'anaesthesia' => ['nullable', 'in:local,general,topical,sedation'],
            'pre_op_notes' => ['nullable', 'string', 'max:5000'],
            'scheduled_at' => ['nullable', 'date'],
            'delegations' => ['nullable', 'array'],
            'delegations.*.doctor_id' => ['required', 'exists:doctors,id'],
            'delegations.*.role' => ['required', 'in:delegate,anesthesia'],
            'delegations.*.service_id' => ['nullable', 'exists:services,id'],
            'delegations.*.service_name' => ['required', 'string', 'max:200'],
            'delegations.*.amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
