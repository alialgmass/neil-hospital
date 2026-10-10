<?php

namespace Modules\Booking\Enums;

enum CallReason: string
{
    case Inquiry = 'inquiry';
    case Booking = 'booking';
    case Reminder = 'reminder';
    case FollowUp = 'follow_up';
    case Complaint = 'complaint';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Inquiry => 'استفسار',
            self::Booking => 'حجز',
            self::Reminder => 'تذكير بموعد',
            self::FollowUp => 'متابعة',
            self::Complaint => 'شكوى',
            self::Other => 'أخرى',
        };
    }
}
