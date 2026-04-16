<?php

namespace Modules\Surgery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordSuppliesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'surgery_id' => ['required', 'exists:surgeries,id'],
            'items' => ['required', 'array', 'min:1'],
        ];
    }
}
