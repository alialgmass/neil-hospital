<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Accounting\Enums\AccountGroup;
use Modules\Accounting\Enums\AccountNature;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.write') ?? false;
    }

    public function rules(): array
    {
        $groups = implode(',', array_column(AccountGroup::cases(), 'value'));
        $natures = implode(',', array_column(AccountNature::cases(), 'value'));

        return [
            'name' => ['required', 'string', 'max:150'],
            'group' => ['required', "in:{$groups}"],
            'nature' => ['required', "in:{$natures}"],
            'parent_id' => ['nullable', 'exists:accounts,id'],
            'is_active' => ['boolean'],
        ];
    }
}
