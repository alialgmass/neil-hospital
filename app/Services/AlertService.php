<?php

namespace App\Services;

use Modules\Booking\Models\Booking;
use Modules\Inventory\Models\InventoryItem;

class AlertService
{
    /**
     * Get all active system alerts.
     */
    public function getAlerts(): array
    {
        return [
            'inventory' => $this->getLowStockAlerts(),
            'finance' => $this->getUnpaidBookingAlerts(),
        ];
    }

    /**
     * Get count of all active alerts.
     */
    public function getAlertCount(): int
    {
        $alerts = $this->getAlerts();

        return count($alerts['inventory']) + count($alerts['finance']);
    }

    /**
     * Findings items below minimum stock.
     */
    private function getLowStockAlerts(): array
    {
        return InventoryItem::whereRaw('quantity <= min_quantity')
            ->where('min_quantity', '>', 0)
            ->get()
            ->map(fn ($item) => [
                'type' => 'low_stock',
                'title' => 'نقص في المخزون',
                'message' => "الصنف ({$item->name}) وصل للحد الأدنى ({$item->quantity} ".($item->unit?->label() ?? '').')',
                'item_id' => $item->id,
                'level' => 'warning',
            ])
            ->toArray();
    }

    /**
     * Find bookings that are unpaid and older than 24 hours.
     */
    private function getUnpaidBookingAlerts(): array
    {
        return Booking::where('pay_status', 'unpaid')
            ->where('visit_date', '<', now()->subDay())
            ->get()
            ->map(fn ($booking) => [
                'type' => 'unpaid_booking',
                'title' => 'حجز غير محصل',
                'message' => "الحجز ({$booking->file_no}) للمريض {$booking->patient_name} لم يتم تحصيله منذ يوم",
                'booking_id' => $booking->id,
                'level' => 'danger',
            ])
            ->toArray();
    }
}
