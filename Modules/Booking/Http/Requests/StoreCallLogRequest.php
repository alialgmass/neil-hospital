<?php

namespace Modules\Booking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Booking\Enums\CallDirection;
use Modules\Booking\Enums\CallOutcome;
use Modules\Booking\Enums\CallReason;

class StoreCallLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::enum(CallDirection::class)],
            'caller_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'file_no' => ['nullable', 'string', 'max:20'],
            'booking_id' => ['nullable', 'string', 'exists:bookings,id'],
            'pre_booking_id' => ['nullable', 'string', 'exists:pre_bookings,id'],
            'reason' => ['required', Rule::enum(CallReason::class)],
            'outcome' => ['required', Rule::enum(CallOutcome::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'follow_up_at' => ['nullable', 'date', 'after:now'],
            'resolves_call_id' => ['nullable', 'string', 'exists:call_logs,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'direction' => 'نوع المكالمة',
            'phone' => 'رقم الهاتف',
            'reason' => 'سبب المكالمة',
            'outcome' => 'نتيجة المكالمة',
            'follow_up_at' => 'موعد المتابعة',
        ];
    }
}
