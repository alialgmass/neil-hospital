<?php

namespace Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Booking\Services\BookingService;

class ArchiveController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService
    ) {}

    public function index(): Response
    {
        $filters = request()->only(['search', 'dept', 'from', 'to']);

        return Inertia::render('admin/Archive', [
            'bookings' => $this->bookingService->getArchive($filters, 30),
            'filters' => $filters,
        ]);
    }
}
