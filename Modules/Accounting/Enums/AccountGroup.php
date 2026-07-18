<?php

namespace Modules\Accounting\Enums;

enum AccountGroup: string
{
    case Assets = 'assets';
    case Liabilities = 'liabilities';
    case Equity = 'equity';
    case Revenues = 'revenues';
    case Expenses = 'expenses';

    public function label(): string
    {
        return match ($this) {
            self::Assets => 'أصول',
            self::Liabilities => 'خصوم',
            self::Equity => 'حقوق الملكية',
            self::Revenues => 'إيرادات',
            self::Expenses => 'مصروفات',
        };
    }

    public function nature(): AccountNature
    {
        return match ($this) {
            self::Assets, self::Expenses => AccountNature::Debit,
            self::Liabilities, self::Equity,
            self::Revenues => AccountNature::Credit,
        };
    }
}
