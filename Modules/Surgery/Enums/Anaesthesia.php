<?php

namespace Modules\Surgery\Enums;

enum Anaesthesia: string
{
    case Local = 'local';
    case General = 'general';
    case Topical = 'topical';
    case Sedation = 'sedation';

    public function label(): string
    {
        return match ($this) {
            self::Local => 'موضعي',
            self::General => 'عام',
            self::Topical => 'موضعي سطحي',
            self::Sedation => 'تخدير خفيف',
        };
    }
}
