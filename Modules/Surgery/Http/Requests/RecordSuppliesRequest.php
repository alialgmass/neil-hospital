<?php

namespace Modules\Surgery\Http\Requests;

use App\Enums\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Surgery\Models\Surgery;

/**
 * Bulk "add supplies" for a surgery / lasik case: any number of individual
 * items plus any number of bundles, recorded atomically in one request.
 */
class RecordSuppliesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can("{$this->dept()->value}.write") ?? false;
    }

    protected function prepareForValidation(): void
    {
        // The case comes from the URL; the body value is optional and must match it.
        if (! $this->filled('surgery_id')) {
            $this->merge(['surgery_id' => $this->route('id')]);
        }
    }

    public function rules(): array
    {
        return [
            'surgery_id' => ['required', 'string', 'in:'.$this->route('id'), 'exists:surgeries,id'],
            'items' => ['nullable', 'array', 'required_without:bundles'],
            'items.*.inventory_item_id' => ['required', 'string', 'exists:inventory,id'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'bundles' => ['nullable', 'array', 'required_without:items'],
            'bundles.*.bundle_id' => ['required', 'string', 'exists:supply_bundles,id'],
            'bundles.*.qty' => ['nullable', 'integer', 'min:1'],
            'bundles.*.selected_items' => ['nullable', 'array'],
            'bundles.*.selected_items.*.inventory_item_id' => ['required', 'string'],
            'bundles.*.selected_items.*.qty' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $surgery = Surgery::find($this->input('surgery_id'));

                if ($surgery && $surgery->dept !== $this->dept()) {
                    $validator->errors()->add('surgery_id', 'هذه الحالة لا تتبع هذا القسم.');
                }

                // Duplicate items are merged (qty summed) — they must share one unit price.
                $firstCostByItem = [];

                foreach ($this->input('items', []) as $index => $item) {
                    $itemId = (string) $item['inventory_item_id'];
                    $unitCost = round((float) $item['unit_cost'], 2);

                    if (! array_key_exists($itemId, $firstCostByItem)) {
                        $firstCostByItem[$itemId] = $unitCost;

                        continue;
                    }

                    if ($firstCostByItem[$itemId] !== $unitCost) {
                        $validator->errors()->add(
                            "items.{$index}.unit_cost",
                            'تم إدخال نفس الصنف أكثر من مرة بسعر مختلف — وحّد السعر أو احذف السطر المكرر.',
                        );
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'items.required_without' => 'أضف صنفاً واحداً أو حزمة واحدة على الأقل.',
            'bundles.required_without' => 'أضف صنفاً واحداً أو حزمة واحدة على الأقل.',
            'items.*.inventory_item_id.required' => 'اختر الصنف.',
            'items.*.inventory_item_id.exists' => 'الصنف المختار غير موجود في المخزن.',
            'items.*.qty.required' => 'أدخل الكمية.',
            'items.*.qty.min' => 'الكمية يجب أن تكون أكبر من صفر.',
            'items.*.unit_cost.min' => 'السعر لا يمكن أن يكون سالباً.',
            'surgery_id.in' => 'رقم الحالة لا يطابق الرابط.',
        ];
    }

    public function attributes(): array
    {
        return [
            'items.*.inventory_item_id' => 'الصنف',
            'items.*.name' => 'اسم الصنف',
            'items.*.qty' => 'الكمية',
            'items.*.unit_cost' => 'السعر',
            'bundles.*.bundle_id' => 'الحزمة',
        ];
    }

    /** Department from the URL prefix (/surgery/… or /lasik/…). */
    public function dept(): Department
    {
        return $this->segment(1) === Department::Lasik->value ? Department::Lasik : Department::Surgery;
    }
}
