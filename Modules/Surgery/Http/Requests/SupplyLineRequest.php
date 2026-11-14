<?php

namespace Modules\Surgery\Http\Requests;

use App\Enums\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Surgery\Models\Surgery;

/**
 * Edit (PUT) or remove (DELETE) one line of a surgery / lasik case's supplies.
 */
class SupplyLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can("{$this->dept()->value}.write") ?? false;
    }

    public function rules(): array
    {
        $rules = [
            'line_ref' => ['required', 'string'],
        ];

        if ($this->isMethod('put')) {
            $rules['qty'] = ['required', 'numeric', 'min:0.01'];
            $rules['unit_cost'] = ['required', 'numeric', 'min:0'];
        }

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $surgery = Surgery::find($this->route('id'));

                if (! $surgery) {
                    abort(404);
                }

                if ($surgery->dept !== $this->dept()) {
                    $validator->errors()->add('line', 'هذه الحالة لا تتبع هذا القسم.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'qty' => 'الكمية',
            'unit_cost' => 'السعر',
        ];
    }

    /** Department from the URL prefix (/surgery/… or /lasik/…). */
    public function dept(): Department
    {
        return $this->segment(1) === Department::Lasik->value ? Department::Lasik : Department::Surgery;
    }
}
