<?php

namespace Modules\HR\Requests;

use App\Concerns\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeePasswordRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => $this->passwordRules(),
        ];
    }

    public function attributes(): array
    {
        return [
            'password' => 'كلمة السر',
        ];
    }
}
