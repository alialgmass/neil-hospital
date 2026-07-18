<?php

namespace Modules\Accounting\Enums;

enum InvoicePayStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'غير مسدد',
            self::Partial => 'مسدد جزئياً',
            self::Paid => 'مسدد',
        };
    }
}
