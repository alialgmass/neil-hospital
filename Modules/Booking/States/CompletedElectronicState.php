<?php

namespace Modules\Booking\States;

/**
 * System-only terminal state: set automatically when the linked Surgery is
 * marked completed from the operations side. Not selectable manually from
 * the booking status UI — see BookingStatusController.
 */
class CompletedElectronicState extends BookingStatus
{
    public static string $name = 'completed_electronic';

    public function label(): string
    {
        return 'مكتمل - إلكتروني';
    }
}
