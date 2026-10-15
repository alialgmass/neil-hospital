<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplyBundleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'max:40'],
            'dept' => ['nullable', 'string', 'in:surgery,lasik,laser'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required', 'string', 'exists:inventory,id'],
            'items.*.item_name' => ['required', 'string', 'max:200'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'أضف صنفاً واحداً على الأقل إلى البند.',
            'items.min' => 'أضف صنفاً واحداً على الأقل إلى البند.',
            'items.*.inventory_item_id.required' => 'اختر الصنف من المخزن لكل سطر.',
            'items.*.inventory_item_id.exists' => 'الصنف المختار غير موجود في المخزن.',
        ];
    }
}
