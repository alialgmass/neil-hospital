<?php

namespace Modules\Booking\Enums;

enum PreBookingStatus: string
{
    case Pending = 'pending';
    case Converted = 'converted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار الاستقبال',
            self::Converted => 'تم تحويله لحجز',
            self::Cancelled => 'ملغي',
        };
    }
}
