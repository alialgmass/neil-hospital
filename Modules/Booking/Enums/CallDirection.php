<?php

namespace Modules\Booking\Enums;

enum CallDirection: string
{
    case Incoming = 'incoming';
    case Outgoing = 'outgoing';

    public function label(): string
    {
        return match ($this) {
            self::Incoming => 'واردة',
            self::Outgoing => 'صادرة',
        };
    }
}
