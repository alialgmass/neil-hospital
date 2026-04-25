<?php

namespace Modules\Labs\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Booking\Models\Booking;

class LabsService
{
    public function getQueue(?string $date = null, ?string $search = null, int $perPage = 25): LengthAwarePaginator
    {
        $date = $date ?? today()->toDateString();

        return Booking::query()
            ->with(['doctor:id,name', 'diagnosticResults'])
            ->where('dept', 'labs')
            ->whereDate('visit_date', $date)
            ->when($search, function ($q, $v) {
                $q->where(function ($iq) use ($v) {
                    $iq->where('patient_name', 'like', "%{$v}%")
                        ->orWhere('file_no', 'like', "%{$v}%");
                });
            })
            ->orderBy('visit_time')
            ->paginate($perPage);
    }
}
