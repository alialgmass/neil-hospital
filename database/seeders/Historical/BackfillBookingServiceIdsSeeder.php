<?php

namespace Database\Seeders\Historical;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\Service;

/**
 * One-time backfill: sets service_id on all historical bookings
 * that have service_id IS NULL based on their service_name.
 */
class BackfillBookingServiceIdsSeeder extends Seeder
{
    public function run(): void
    {
        $allServices = Service::all()->keyBy('name');
        $allServicesNormalized = $allServices->keyBy(fn ($s) => $this->normalizeArabic($s->name));

        $bookings = DB::table('bookings')
            ->whereNull('service_id')
            ->whereNotNull('service_name')
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($bookings as $booking) {
            $serviceId = $this->resolveServiceId($booking->service_name, $allServices, $allServicesNormalized);

            if ($serviceId) {
                DB::table('bookings')->where('id', $booking->id)->update(['service_id' => $serviceId]);
                $updated++;
            } else {
                $skipped++;
                $this->command->line("  ⚠ No match for: {$booking->service_name} ({$booking->file_no})");
            }
        }

        $this->command->line("  ✓ Backfill: {$updated} updated, {$skipped} unmatched");
    }

    private function normalizeArabic(string $text): string
    {
        $text = str_replace(['أ', 'إ', 'آ', 'ة', 'ى', 'ؤ', 'ئ'], ['ا', 'ا', 'ا', 'ه', 'ي', 'و', 'ي'], $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    private function resolveServiceId(string $serviceName, $allServices, $allServicesNormalized): ?string
    {
        // 1. Exact match
        if (isset($allServices[$serviceName])) {
            return $allServices[$serviceName]->id;
        }

        // 2. Normalized exact match
        $normalized = $this->normalizeArabic($serviceName);
        if (isset($allServicesNormalized[$normalized])) {
            return $allServicesNormalized[$normalized]->id;
        }

        // 3. Substring match: booking name contains service name
        foreach ($allServices as $service) {
            if (str_contains($serviceName, $service->name)) {
                return $service->id;
            }
        }

        // 4. Substring match: service name contains booking name
        foreach ($allServices as $service) {
            if (str_contains($service->name, $serviceName)) {
                return $service->id;
            }
        }

        // 5. Normalized substring match
        foreach ($allServices as $service) {
            $normalizedService = $this->normalizeArabic($service->name);
            if (str_contains($normalized, $normalizedService) || str_contains($normalizedService, $normalized)) {
                return $service->id;
            }
        }

        return null;
    }
}
