<?php

namespace Modules\Inventory\Http\Requests;

use App\Enums\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Booking\Enums\PayMethod;

class StoreItemSalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_date' => 'required|date',
            'customer_name' => 'required|string|max:200',
            'customer_phone' => 'nullable|string|max:30',
            'file_no' => 'nullable|string|max:50',
            'department' => ['nullable', Rule::enum(Department::class)],
            'pay_method' => ['required', Rule::enum(PayMethod::class)->only([PayMethod::Cash, PayMethod::Card, PayMethod::Transfer])],
            'discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|string|exists:inventory,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_name' => 'اسم العميل',
            'pay_method' => 'طريقة الدفع',
            'items' => 'الأصناف',
            'items.*.item_id' => 'الصنف',
            'items.*.qty' => 'الكمية',
            'items.*.unit_price' => 'سعر البيع',
        ];
    }
}
