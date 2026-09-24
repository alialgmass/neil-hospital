<?php

namespace Modules\Insurance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Insurance\Enums\PriceListType;

/**
 * Editable price-list fields. Identity / bookkeeping columns (id,
 * timestamps, price_list_id on items) are never accepted.
 */
class UpdatePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('insurance.price_lists.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(PriceListType::class)],
            'ins_company_id' => ['nullable', 'string', 'exists:insurance_companies,id'],
            'ins_coverage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'items' => ['present', 'array'],
            'items.*.service_id' => ['required', 'string', 'distinct', 'exists:services,id'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.service_id.distinct' => 'هذه الخدمة مكررة في القائمة.',
            'items.*.service_id.required' => 'اختر الخدمة.',
            'items.*.price.min' => 'السعر لا يمكن أن يكون سالباً.',
            'ins_coverage.max' => 'نسبة التغطية لا تتجاوز 100%.',
            'discount_pct.max' => 'نسبة الخصم لا تتجاوز 100%.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم القائمة',
            'type' => 'نوع القائمة',
            'ins_company_id' => 'شركة التأمين',
            'ins_coverage' => 'نسبة التغطية',
            'discount_pct' => 'نسبة الخصم',
            'items.*.service_id' => 'الخدمة',
            'items.*.price' => 'السعر',
        ];
    }
}
