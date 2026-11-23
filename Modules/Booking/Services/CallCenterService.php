<?php

namespace Modules\Booking\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Booking\Enums\CallReason;
use Modules\Booking\Enums\PreBookingStatus;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\CallLog;
use Modules\Booking\Models\PreBooking;
use Modules\Booking\States\CancelledState;

class CallCenterService
{
    /**
     * @param  array{date?: ?string, search?: ?string}  $filters
     */
    public function calls(array $filters, int $perPage = 30): LengthAwarePaginator
    {
        return CallLog::query()
            ->with('creator:id,name')
            ->when($filters['date'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', $date))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('phone', 'like', "%{$search}%")
                        ->orWhere('caller_name', 'like', "%{$search}%")
                        ->orWhere('file_no', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage, pageName: 'calls_page')
            ->withQueryString();
    }

    /** @return Collection<int, PreBooking> */
    public function preBookings(?PreBookingStatus $status = null): Collection
    {
        return PreBooking::query()
            ->with(['service:id,name', 'doctor:id,name', 'insuranceCompany:id,name', 'creator:id,name', 'booking:id,file_no'])
            ->when($status, fn (Builder $query, PreBookingStatus $status) => $query->where('status', $status))
            ->orderBy('preferred_date')
            ->orderBy('preferred_time')
            ->limit(200)
            ->get();
    }

    /**
     * Pending preliminary bookings for reception, oldest appointment first.
     *
     * @return Collection<int, PreBooking>
     */
    public function pendingForReception(): Collection
    {
        return PreBooking::pending()
            ->with(['service:id,name', 'doctor:id,name', 'insuranceCompany:id,name', 'creator:id,name'])
            ->orderBy('preferred_date')
            ->orderBy('preferred_time')
            ->get();
    }

    /**
     * Non-cancelled bookings on $date to call and remind, each with the
     * outcome of the latest reminder call already made for it (if any).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function reminders(string $date): Collection
    {
        $bookings = Booking::query()
            ->whereDate('visit_date', $date)
            ->whereNotState('status', CancelledState::class)
            ->with('doctor:id,name')
            ->orderBy('visit_time')
            ->get(['id', 'file_no', 'patient_name', 'patient_phone', 'dept', 'service_name', 'visit_date', 'visit_time', 'doctor_id']);

        $lastReminders = CallLog::query()
            ->whereIn('booking_id', $bookings->pluck('id'))
            ->where('reason', CallReason::Reminder)
            ->latest()
            ->get(['booking_id', 'outcome', 'created_at'])
            ->unique('booking_id')
            ->keyBy('booking_id');

        return $bookings->map(fn (Booking $booking) => [
            'id' => $booking->id,
            'file_no' => $booking->file_no,
            'patient_name' => $booking->patient_name,
            'patient_phone' => $booking->patient_phone,
            'dept' => $booking->dept?->value,
            'service_name' => $booking->service_name,
            'visit_time' => $booking->visit_time,
            'doctor_name' => $booking->doctor?->name,
            'last_reminder_outcome' => $lastReminders->get($booking->id)?->outcome?->value,
            'last_reminder_at' => $lastReminders->get($booking->id)?->created_at?->toDateTimeString(),
        ]);
    }

    /**
     * Calls whose follow-up is due by the end of today and not yet done.
     *
     * @return Collection<int, CallLog>
     */
    public function dueFollowUps(): Collection
    {
        return CallLog::query()
            ->whereNotNull('follow_up_at')
            ->whereNull('follow_up_done_at')
            ->where('follow_up_at', '<=', now()->endOfDay())
            ->with('creator:id,name')
            ->orderBy('follow_up_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordCall(array $data, int $userId): CallLog
    {
        return DB::transaction(function () use ($data, $userId) {
            $resolvesCallId = $data['resolves_call_id'] ?? null;
            unset($data['resolves_call_id']);

            if (! empty($data['booking_id']) && empty($data['file_no'])) {
                $data['file_no'] = Booking::whereKey($data['booking_id'])->value('file_no');
            }

            $call = CallLog::create([...$data, 'created_by' => $userId]);

            if ($resolvesCallId) {
                CallLog::whereKey($resolvesCallId)
                    ->whereNull('follow_up_done_at')
                    ->update(['follow_up_done_at' => now()]);
            }

            return $call;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPreBooking(array $data, int $userId): PreBooking
    {
        return PreBooking::create([
            ...$data,
            'status' => PreBookingStatus::Pending,
            'created_by' => $userId,
        ]);
    }

    public function cancelPreBooking(string $id, int $userId): PreBooking
    {
        $preBooking = PreBooking::whereKey($id)->lockForUpdate()->firstOrFail();

        if ($preBooking->status !== PreBookingStatus::Pending) {
            throw ValidationException::withMessages([
                'pre_booking' => 'لا يمكن إلغاء حجز مبدئي تم تحويله أو إلغاؤه بالفعل.',
            ]);
        }

        $preBooking->update([
            'status' => PreBookingStatus::Cancelled,
            'handled_by' => $userId,
            'handled_at' => now(),
        ]);

        return $preBooking;
    }

    /**
     * Called by reception after creating a real booking from a pending
     * preliminary booking.
     */
    public function markConverted(string $preBookingId, Booking $booking, int $userId): void
    {
        $preBooking = PreBooking::whereKey($preBookingId)->lockForUpdate()->firstOrFail();

        if ($preBooking->status !== PreBookingStatus::Pending) {
            throw ValidationException::withMessages([
                'pre_booking_id' => 'هذا الحجز المبدئي تم تحويله أو إلغاؤه بالفعل.',
            ]);
        }

        $preBooking->update([
            'status' => PreBookingStatus::Converted,
            'booking_id' => $booking->id,
            'handled_by' => $userId,
            'handled_at' => now(),
        ]);
    }

    /**
     * Known patients matching a phone, name or file number — identity only,
     * no amounts — so the call center can recognise a caller.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function lookupPatients(string $term): Collection
    {
        return Booking::query()
            ->where(function (Builder $query) use ($term) {
                $query->where('patient_phone', 'like', "%{$term}%")
                    ->orWhere('patient_name', 'like', "%{$term}%")
                    ->orWhere('file_no', 'like', "%{$term}%");
            })
            ->latest()
            ->limit(50)
            ->get(['file_no', 'patient_name', 'patient_phone', 'national_id', 'visit_date', 'dept'])
            ->unique(fn (Booking $booking) => $booking->national_id ?: $booking->file_no)
            ->take(10)
            ->map(fn (Booking $booking) => [
                'file_no' => $booking->file_no,
                'patient_name' => $booking->patient_name,
                'patient_phone' => $booking->patient_phone,
                'national_id' => $booking->national_id,
                'last_visit' => $booking->visit_date?->toDateString(),
                'last_dept' => $booking->dept?->value,
            ])
            ->values();
    }
}
