<?php

namespace Modules\Booking\Enums;

enum CallOutcome: string
{
    case Resolved = 'resolved';
    case PreBooked = 'pre_booked';
    case Confirmed = 'confirmed';
    case Rescheduled = 'rescheduled';
    case Cancelled = 'cancelled';
    case NoAnswer = 'no_answer';
    case CallBack = 'call_back';

    public function label(): string
    {
        return match ($this) {
            self::Resolved => 'تم الرد / حُلّت',
            self::PreBooked => 'تم حجز مبدئي',
            self::Confirmed => 'أكد الموعد',
            self::Rescheduled => 'طلب تغيير الموعد',
            self::Cancelled => 'ألغى الموعد',
            self::NoAnswer => 'لم يرد',
            self::CallBack => 'يُعاد الاتصال',
        };
    }
}
