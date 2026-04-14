<?php

namespace Modules\Booking\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Booking\Actions\CancelBookingAction;
use Modules\Booking\Actions\CreateBookingAction;
use Modules\Booking\Actions\UpdateBookingAction;
use Modules\Booking\DTOs\BookingData;
use Modules\Booking\DTOs\BookingFilterData;
use Modules\Booking\Http\Requests\StoreBookingRequest;
use Modules\Booking\Http\Requests\UpdateBookingRequest;
use Modules\Booking\Repositories\Contracts\BookingRepositoryInterface;
use Modules\Booking\Services\BookingService;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly CreateBookingAction $createAction,
        private readonly UpdateBookingAction $updateAction,
        private readonly CancelBookingAction $cancelAction,
        private readonly BookingRepositoryInterface $bookingRepository,
    ) {}

    public function index(): Response
    {
        $filter = BookingFilterData::fromArray(request()->all());
        $bookings = $this->bookingService->list($filter);

        return Inertia::render('booking/Index', [
            'bookings' => $bookings,
            'filters'  => request()->only(['date', 'dept', 'status', 'pay_status', 'search']),
            'todayStats' => $this->bookingRepository->countByDeptForDate(today()->toDateString()),
        ]);
    }

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $data = BookingData::fromArray($request->validated());
        $booking = $this->createAction->execute($data, $request->user()->id);

        return redirect()->route('booking.index')
            ->with('success', "تم تسجيل الحجز بنجاح — {$booking->file_no}");
    }

    public function update(UpdateBookingRequest $request, string $id): RedirectResponse
    {
        $data = BookingData::fromArray($request->validated());
        $this->updateAction->execute($id, $data);

        return back()->with('success', 'تم تحديث الحجز بنجاح.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->cancelAction->execute(
            id:            $id,
            cancelReason:  request('cancel_reason', 'حذف من قبل المستخدم'),
            adminOverride: request()->user()->hasRole('admin'),
        );

        return back()->with('success', 'تم إلغاء الحجز.');
    }

    public function receipt(string $id): Response
    {
        $booking = $this->bookingService->findOrFail($id);

        return Inertia::render('booking/Receipt', [
            'booking' => $booking->load(['doctor:id,name', 'service:id,name']),
        ]);
    }
}
