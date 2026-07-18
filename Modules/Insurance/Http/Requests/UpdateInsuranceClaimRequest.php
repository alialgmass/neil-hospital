<?php

namespace Modules\Insurance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Insurance\States\ApprovedState;
use Modules\Insurance\States\DraftState;
use Modules\Insurance\States\PaidState;
use Modules\Insurance\States\RejectedState;
use Modules\Insurance\States\SubmittedState;

class UpdateInsuranceClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('insurance.write') ?? false;
    }

    public function rules(): array
    {
        $statuses = implode(',', [
            DraftState::$name,
            SubmittedState::$name,
            ApprovedState::$name,
            RejectedState::$name,
            PaidState::$name,
        ]);

        return [
            'status' => ['required', "in:{$statuses}"],
            'approved_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'rejection_reason' => ['nullable', 'string'],
            'submission_date' => ['nullable', 'date'],
            'approval_date' => ['nullable', 'date'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
