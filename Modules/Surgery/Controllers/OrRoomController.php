<?php

namespace Modules\Surgery\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Surgery\Services\SurgeryService;

class OrRoomController extends Controller
{
    public function __construct(
        private readonly SurgeryService $surgeryService
    ) {}

    public function index(): JsonResponse
    {
        $dept = request('dept', 'surgery');
        $date = request('date', today()->toDateString());

        $rooms = $this->surgeryService->getOrRoomsWithBedStatus($dept, $date);

        return response()->json($rooms);
    }

    public function availableBeds(): JsonResponse
    {
        $dept = request('dept', 'surgery');
        $date = request('date', today()->toDateString());

        $beds = $this->surgeryService->getAvailableBeds($dept, $date);

        return response()->json($beds);
    }
}
