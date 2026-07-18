<?php

namespace Modules\Admin\Http\Requests;

use App\Enums\Department;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Inventory\Enums\CenterShareType;
use Modules\Inventory\Enums\ServiceStatus;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin.services') ?? false;
    }

    public function rules(): array
    {
        $depts = implode(',', array_column(Department::cases(), 'value'));
        $shareTypes = implode(',', array_column(CenterShareType::cases(), 'value'));
        $statuses = implode(',', array_column(ServiceStatus::cases(), 'value'));

        return [
            'name' => ['required', 'string', 'max:200'],
            'dept' => ['required', "in:{$depts}"],
            'price' => ['nullable', 'numeric', 'min:0'],
            'ins_price' => ['nullable', 'numeric', 'min:0'],
            'center_type' => ['required', "in:{$shareTypes}"],
            'center_val' => ['nullable', 'numeric', 'min:0'],
            'duration_mins' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', "in:{$statuses}"],
            'revenue_account_id' => ['nullable', 'ulid', 'exists:accounts,id'],
        ];
    }
}
