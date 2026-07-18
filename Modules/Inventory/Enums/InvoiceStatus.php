<?php

namespace Modules\Inventory\Enums;

enum InvoiceStatus: string
{
    case Paid = 'paid';
    case Partial = 'partial';
    case Unpaid = 'unpaid';
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'مدفوع',
            self::Partial => 'مدفوع جزئياً',
            self::Unpaid => 'غير مدفوع',
            self::Posted => 'مرحّل',
            self::Cancelled => 'ملغي',
        };
    }
}
