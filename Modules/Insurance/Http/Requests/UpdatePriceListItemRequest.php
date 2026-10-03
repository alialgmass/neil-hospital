<?php

namespace Modules\Insurance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePriceListItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('insurance.price_lists.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
