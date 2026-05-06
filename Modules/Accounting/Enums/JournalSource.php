<?php

namespace Modules\Accounting\Enums;

enum JournalSource: string
{
    case MANUAL = 'manual';
    case BOOKING = 'booking';
    case AUTO_BOOKING = 'auto_booking';
    case PURCHASE = 'purchase';
    case EXPENSE = 'expense';
    case SALARY = 'salary';
    case SETTLEMENT = 'settlement';
    case INSURANCE_CLAIM = 'insurance_claim';
    case INSURANCE_COLLECT = 'insurance_collect';
    case SUPPLIES_USED = 'supplies_used';
    case DOCTOR_SHIFT = 'doctor_shift';
    case DOCTOR_PAYMENT = 'doctor_payment';
    case SUPPLIER_PAYMENT = 'supplier_payment';
    case REVERSAL = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'يدوي',
            self::BOOKING => 'حجز',
            self::AUTO_BOOKING => 'حجز تلقائي',
            self::PURCHASE => 'مشتريات',
            self::EXPENSE => 'مصروفات',
            self::SALARY => 'رواتب',
            self::SETTLEMENT => 'تسوية',
            self::INSURANCE_CLAIM => 'مطالبة تأمين',
            self::INSURANCE_COLLECT => 'تحصيل تأمين',
            self::SUPPLIES_USED => 'صرف مستلزمات',
            self::DOCTOR_SHIFT => 'شيفت طبيب',
            self::DOCTOR_PAYMENT => 'صرف مستحقات طبيب',
            self::SUPPLIER_PAYMENT => 'سداد مورد',
            self::REVERSAL => 'عكس قيد (إلغاء)',
        };
    }
}
