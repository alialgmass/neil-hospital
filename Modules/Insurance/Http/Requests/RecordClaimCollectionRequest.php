<?php

namespace Modules\Insurance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordClaimCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('insurance.collect') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'discount' => ['required', 'numeric', 'min:0', 'lte:amount'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
