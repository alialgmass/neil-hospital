<?php

namespace Modules\Booking\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Booking\Actions\UpdateBookingStatusAction;

class BookingStatusController extends Controller
{
    public function __construct(
        private readonly UpdateBookingStatusAction $updateStatusAction,
    ) {}

    public function update(string $id): RedirectResponse
    {
        $this->updateStatusAction->execute(
            id: $id,
            newStatus: request()->input('status'),
            cancelReason: request()->input('cancel_reason'),
        );

        return back()->with('success', 'تم تحديث حالة الحجز.');
    }
}
