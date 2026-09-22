<?php

namespace Modules\Booking\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Admin\Services\ActivityLogService;
use Modules\Booking\Actions\CreateBookingAction;
use Modules\Booking\Actions\UpdateBookingAction;
use Modules\Booking\DTOs\BookingData;
use Modules\Booking\DTOs\BookingFilterData;
use Modules\Booking\Exports\BookingsExport;
use Modules\Booking\Http\Requests\StoreBookingRequest;
use Modules\Booking\Http\Requests\UpdateBookingRequest;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Booking\Repositories\Contracts\BookingRepositoryInterface;
use Modules\Booking\Services\BookingService;
use Modules\Booking\States\CompletedElectronicState;
use Modules\Booking\States\CompletedState;
use Modules\Doctor\Actions\SyncDoctorEntitlementAction;
use Modules\Surgery\Services\SurgeryService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly SurgeryService $surgeryService,
        private readonly CreateBookingAction $createAction,
        private readonly UpdateBookingAction $updateAction,
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function index(): Response
    {
        $filter = BookingFilterData::fromArray(request()->all());
        $filterDate = request('date') ?? today()->toDateString();
        $formResources = $this->bookingService->getFormResources();

        return Inertia::render('booking/Index', [
            'bookings' => $this->bookingService->list($filter),
            'filters' => request()->only(['date', 'date_from', 'date_to', 'dept', 'status', 'pay_status', 'search']),
            'todayStats' => $this->bookingRepository->countByDeptForDate(today()->toDateString()),
            'services' => $formResources['services'],
            'insuranceCompanies' => $formResources['insuranceCompanies'],
            'priceLists' => $formResources['priceLists'],
            'doctors' => $formResources['doctors'],
            'orRooms' => $this->surgeryService->getOrRoomsForDate($filterDate),
            'today' => today()->toDateString(),
        ]);
    }

    public function export()
    {
        $filter = BookingFilterData::fromArray(request()->all());

        $bookings = $this->bookingRepository->filteredAll($filter);

        return Excel::download(new BookingsExport($bookings), 'reservations.xlsx');
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
        $booking = $this->bookingRepository->findOrFail($id);
        $wasCompleted = $booking->status instanceof CompletedState || $booking->status instanceof CompletedElectronicState;

        if ($wasCompleted && ! $request->user()->can('booking.edit_completed')) {
            return back()->withErrors(['status' => 'لا يمكن تعديل حجز مكتمل.']);
        }

        $data = BookingData::fromArray($request->validated());
        $this->updateAction->execute($id, $data);

        if ($wasCompleted) {
            $this->activityLog->log(
                'edited_while_completed',
                'booking',
                $id,
                "تعديل حجز مكتمل: {$booking->file_no}",
            );
        }

        return back()->with('success', 'تم تحديث الحجز بنجاح.');
    }

    public function destroy(string $id, SyncDoctorEntitlementAction $syncDoctorEntitlement): RedirectResponse
    {
        $booking = $this->bookingRepository->findOrFail($id);

        $syncDoctorEntitlement->onBookingDeleted($booking);
        $this->bookingRepository->delete($id);

        return back()->with('success', 'تم حذف الحجز بنجاح.');
    }

    public function receipt(string $id): Response
    {
        $booking = $this->bookingService->findOrFail($id);

        return Inertia::render('booking/Receipt', [
            'booking' => $booking->load(['doctor:id,name', 'service:id,name']),
        ]);
    }

    public function barcode(string $id): Response
    {
        $booking = $this->bookingService->findOrFail($id);

        return Inertia::render('booking/Barcode', [
            'booking' => $booking->load(['doctor:id,name']),
        ]);
    }

    public function searchPatients(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if ($term === '') {
            return response()->json([]);
        }

        $matches = Booking::query()
            ->where('patient_name', 'like', "%{$term}%")
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get(['file_no', 'patient_name', 'national_id', 'patient_phone', 'patient_age', 'gender', 'kinship_degree']);

        $patients = $matches
            ->unique(fn (Booking $b) => $b->national_id ?: $b->file_no)
            ->take(10)
            ->map(fn (Booking $b) => [
                'file_no' => $b->file_no,
                'patient_name' => $b->patient_name,
                'national_id' => $b->national_id,
                'patient_phone' => $b->patient_phone,
                'patient_age' => $b->patient_age,
                'gender' => $b->gender,
                'kinship_degree' => $b->kinship_degree?->value,
            ])
            ->values();

        return response()->json($patients);
    }

    public function patientFile(string $fileNo): Response
    {
        $bookings = $this->bookingService->getPatientFile($fileNo);
        $patient = $bookings->first();

        $bookings->each(function (Booking $booking) {
            $booking->media_files = $booking->getMedia('archive-files')->map(fn (Media $m) => [
                'id' => $m->id,
                'name' => $m->file_name,
                'url' => $m->getUrl(),
                'mime' => $m->mime_type,
                'size' => $m->human_readable_size,
            ]);
        });

        return Inertia::render('booking/PatientFile', [
            'file_no' => $fileNo,
            'transfer_services' => Service::active()
                ->whereIn('dept', ['surgery', 'lasik', 'laser'])
                ->orderBy('dept')
                ->orderBy('name')
                ->get(['id', 'name', 'dept']),
            'patient' => $patient ? [
                'name' => $patient->patient_name,
                'phone' => $patient->patient_phone,
                'age' => $patient->patient_age,
                'file_no' => $patient->file_no,
                'national_id' => $patient->national_id,
                'gender' => $patient->gender,
                'kinship_degree' => $patient->kinship_degree?->value,
                'kinship_degree_label' => $patient->kinship_degree?->label(),
            ] : null,
            'bookings' => $bookings,
        ]);
    }
}
