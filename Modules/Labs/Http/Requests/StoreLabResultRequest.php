<?php

namespace Modules\Labs\Http\Requests;

use App\Enums\EyeSide;
use Illuminate\Foundation\Http\FormRequest;

class StoreLabResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('labs.write') ?? false;
    }

    public function rules(): array
    {
        $eyes = implode(',', array_column(EyeSide::cases(), 'value'));

        return [
            'test_name' => ['required', 'string', 'max:150'],
            'eye' => ['nullable', "in:{$eyes}"],
            'result_text' => ['nullable', 'string'],
            'result_values' => ['nullable', 'array'],
            'doctor_notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'test_name.required' => 'اسم الفحص مطلوب.',
        ];
    }
}
