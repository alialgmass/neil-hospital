<?php

namespace App\Enums;

enum AnalysisType: string
{
    case Negative = 'negative';
    case Positive = 'positive';

    public function label(): string
    {
        return match ($this) {
            self::Negative => 'Negative',
            self::Positive => 'Positive',
        };
    }
}
