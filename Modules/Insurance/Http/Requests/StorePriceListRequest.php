<?php

namespace Modules\Insurance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Insurance\Enums\PriceListType;

class StorePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('insurance.write') ?? false;
    }

    public function rules(): array
    {
        $types = implode(',', array_column(PriceListType::cases(), 'value'));

        return [
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', "in:{$types}"],
            'ins_company_id' => ['nullable', 'string', 'exists:insurance_companies,id'],
            'ins_coverage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.service_id' => ['required', 'string', 'exists:services,id'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
