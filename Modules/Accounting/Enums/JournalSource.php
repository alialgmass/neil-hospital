<?php

namespace Modules\Accounting\Enums;

enum JournalSource: string
{
    case MANUAL = 'manual';
    case BOOKING = 'booking';
    case PURCHASE = 'purchase';
    case EXPENSE = 'expense';
    case SALARY = 'salary';
    case SETTLEMENT = 'settlement';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'يدوي',
            self::BOOKING => 'حجز',
            self::PURCHASE => 'مشتريات',
            self::EXPENSE => 'مصروفات',
            self::SALARY => 'رواتب',
            self::SETTLEMENT => 'تسوية',
        };
    }
}
