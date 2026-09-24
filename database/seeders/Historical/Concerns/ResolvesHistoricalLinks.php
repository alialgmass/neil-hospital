<?php

namespace Database\Seeders\Historical\Concerns;

use Modules\Doctor\Models\Doctor;
use Modules\Inventory\Models\Service;

/**
 * Links a historical booking row to a real `doctors` / `services` record.
 *
 * The source sheet (جهار 1 (1).xlsx) spells doctor and service names
 * inconsistently and there is no canonical services list, so a booking row
 * would otherwise be created with `doctor_id = null` and an invalid
 * `service_id`. Both resolvers normalise the name, match an existing record,
 * and — only when nothing matches — create a minimal record from the sheet
 * value so every historical booking ends up linked.
 */
trait ResolvesHistoricalLinks
{
    use NormalizesArabic;

    /** @var array<string, string>|null normalised doctor name => id */
    private ?array $doctorIndex = null;

    /** @var array<string, string> "dept|normalised name" => id */
    private array $serviceIndex = [];

    /** Names in the sheet's "doctor" column that are payers/centres, not doctors. */
    private function nonDoctorTokens(): array
    {
        return ['تامين صحي', 'تامين صحى', 'مركز النيل', 'الرمد', 'بنك الشفاء', 'البنك الاهلي', 'لا يوجد', 'لايوجد'];
    }

    protected function resolveDoctorId(?string $name): ?string
    {
        $name = $name !== null ? trim($name) : '';
        $norm = $this->normalizeArabic($name);

        if ($norm === '' || in_array($norm, $this->nonDoctorTokens(), true)) {
            return null;
        }

        if ($this->doctorIndex === null) {
            $this->doctorIndex = [];
            foreach (Doctor::all(['id', 'name']) as $doc) {
                $this->indexDoctor($doc->id, (string) $doc->name);
            }
        }

        $key = $this->spaceless($norm);

        foreach ([$norm, $key, $this->stripHonorific($norm), $this->spaceless($this->stripHonorific($norm))] as $lookup) {
            if (isset($this->doctorIndex[$lookup])) {
                return $this->doctorIndex[$lookup];
            }
        }

        $doctor = Doctor::create([
            'name' => str_starts_with($name, 'د') ? $name : 'د. '.$name,
            'fee_type' => 'percentage',
            'fee_value' => 0,
            'is_active' => true,
            'notes' => 'أُنشئ تلقائيًا من بيانات تاريخية (جهار 1)',
        ]);
        $this->indexDoctor($doctor->id, (string) $doctor->name);

        return $doctor->id;
    }

    protected function resolveServiceId(string $name, string $dept, float $price = 0.0): string
    {
        $name = trim($name) !== '' ? trim($name) : 'خدمة غير محددة';
        $norm = $this->normalizeArabic($name);
        $cacheKey = $dept.'|'.$norm;

        if (isset($this->serviceIndex[$cacheKey])) {
            return $this->serviceIndex[$cacheKey];
        }

        $existing = Service::where('dept', $dept)->get(['id', 'name'])
            ->first(fn (Service $s) => $this->normalizeArabic((string) $s->name) === $norm);

        if ($existing) {
            return $this->serviceIndex[$cacheKey] = $existing->id;
        }

        $service = Service::create([
            'name' => $name,
            'dept' => $dept,
            'price' => $price > 0 ? $price : $this->defaultServicePrice($dept),
            'status' => 'active',
        ]);

        return $this->serviceIndex[$cacheKey] = $service->id;
    }

    private function indexDoctor(string $id, string $name): void
    {
        $norm = $this->normalizeArabic($name);
        foreach ([$norm, $this->spaceless($norm), $this->stripHonorific($norm), $this->spaceless($this->stripHonorific($norm))] as $key) {
            $this->doctorIndex[$key] ??= $id;
        }
    }

    private function stripHonorific(string $normalized): string
    {
        return trim(preg_replace('/^(د\s*[\/.]?\s*|دكتوره?\s+|الدكتوره?\s+)/u', '', $normalized) ?? $normalized);
    }

    private function spaceless(string $value): string
    {
        return str_replace(' ', '', $value);
    }

    private function defaultServicePrice(string $dept): float
    {
        return match ($dept) {
            'surgery' => 6500.0,
            'lasik' => 15000.0,
            'laser' => 1000.0,
            'labs' => 650.0,
            default => 300.0,
        };
    }
}
