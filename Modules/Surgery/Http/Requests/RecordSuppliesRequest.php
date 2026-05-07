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
            'items' => ['nullable', 'array'],
            'items.*.inventory_item_id' => ['required', 'string'],
            'items.*.name' => ['required', 'string'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'bundles' => ['nullable', 'array'],
            'bundles.*.bundle_id' => ['required', 'string', 'exists:supply_bundles,id'],
            'bundles.*.qty' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
