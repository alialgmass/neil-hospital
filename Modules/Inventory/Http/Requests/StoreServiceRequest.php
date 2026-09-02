<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:200',
            'dept' => 'required|in:clinic,labs,surgery,lasik,laser,pentacam',
            'price' => 'nullable|numeric|min:0',
            'one_eye_price' => 'nullable|numeric|min:0',
            'both_eyes_price' => 'nullable|numeric|min:0',
            'ins_price' => 'nullable|numeric|min:0',
            'center_type' => 'required|in:pct,fixed',
            'center_val' => 'nullable|numeric|min:0',
            'duration_mins' => 'nullable|integer|min:1',
            'status' => 'nullable|in:active,inactive',
        ];
    }
}
