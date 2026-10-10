<?php

namespace Modules\Booking\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Modules\Booking\Actions\UpdateBookingStatusAction;
use Modules\Booking\States\CompletedElectronicState;

class BookingStatusController extends Controller
{
    public function __construct(
        private readonly UpdateBookingStatusAction $updateStatusAction,
    ) {}

    public function update(string $id): RedirectResponse
    {
        // "مكتمل - إلكتروني" is a system-only status set by surgery completion —
        // never selectable manually from the booking status UI.
        if (request()->input('status') === CompletedElectronicState::$name) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن اختيار هذه الحالة يدوياً.',
            ]);
        }

        $this->updateStatusAction->execute(
            id: $id,
            newStatus: request()->input('status'),
            cancelReason: request()->input('cancel_reason'),
        );

        return back()->with('success', 'تم تحديث حالة الحجز.');
    }
}
