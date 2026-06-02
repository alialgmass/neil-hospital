<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Accounting\Enums\CostCenter;

class StoreJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('journal.write') ?? false;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:300'],
            'debit_account_id' => ['required', 'exists:accounts,id'],
            'credit_account_id' => ['required', 'exists:accounts,id', 'different:debit_account_id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:80'],
            'cost_center' => ['nullable', new Enum(CostCenter::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'تاريخ القيد مطلوب.',
            'date.date' => 'التاريخ غير صالح.',
            'description.required' => 'البيان مطلوب.',
            'description.max' => 'البيان لا يتجاوز 300 حرف.',
            'debit_account_id.required' => 'الحساب المدين مطلوب.',
            'debit_account_id.exists' => 'الحساب المدين غير موجود.',
            'credit_account_id.required' => 'الحساب الدائن مطلوب.',
            'credit_account_id.exists' => 'الحساب الدائن غير موجود.',
            'credit_account_id.different' => 'لا يمكن أن يكون المدين والدائن نفس الحساب.',
            'amount.required' => 'المبلغ مطلوب.',
            'amount.numeric' => 'المبلغ يجب أن يكون رقماً.',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر.',
            'reference.max' => 'رقم المرجع لا يتجاوز 80 حرفاً.',
        ];
    }
}
