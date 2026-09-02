<?php

namespace Modules\Booking\Services;

use App\Enums\EyeSide;
use Modules\Booking\Models\Service;

/**
 * Resolves the price for a service based on the selected eye side.
 *
 * The booking price is ALWAYS derived server-side from the service's
 * one-eye / both-eyes prices — the client-submitted price is never trusted
 * whenever a service is selected. A client price is only honoured as a
 * fallback for service-less bookings (e.g. manual walk-in charges).
 */
class ServicePricingService
{
    /**
     * The price for the given service + eye side, or null when the service
     * has no price configured at all (neither eye price nor legacy price).
     *
     * A configured price of 0 is a real price and is returned as 0.0 — it is
     * never treated as "unconfigured".
     */
    public function resolveEyePrice(Service $service, ?EyeSide $eyeSide): ?float
    {
        $legacy = $service->price !== null ? (float) $service->price : null;
        $oneEye = $service->one_eye_price !== null ? (float) $service->one_eye_price : $legacy;

        if ($eyeSide === EyeSide::OU) {
            $bothEyes = $service->both_eyes_price !== null
                ? (float) $service->both_eyes_price
                : ($oneEye !== null ? $oneEye * 2 : null);

            return $bothEyes;
        }

        return $oneEye;
    }

    /**
     * Resolve the server-side price for a booking request.
     *
     * When a valid service is selected the returned price is derived purely
     * from that service and the eye side; the client `$fallback` is ignored.
     * The `$fallback` is only returned when no service is selected, the
     * service does not exist, or the service has no price configured.
     */
    public function priceFor(?string $serviceId, ?string $eyeSide, ?float $fallback = null): ?float
    {
        if (! $serviceId) {
            return $fallback;
        }

        $service = Service::find($serviceId);

        if (! $service) {
            return $fallback;
        }

        $eye = $eyeSide ? EyeSide::tryFrom($eyeSide) : null;

        return $this->resolveEyePrice($service, $eye) ?? $fallback;
    }
}
