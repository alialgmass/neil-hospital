<?php

namespace Modules\Admin\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Modules\Insurance\Models\InsuranceCompany;
use Modules\Insurance\Models\PriceList;
use Modules\Inventory\Models\Service;

class PriceListImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $name = trim((string) ($row['اسم القائمة'] ?? $row['name'] ?? ''));

            if ($name === '') {
                $this->skipped++;

                continue;
            }

            $serviceName = trim((string) ($row['الخدمة'] ?? $row['service'] ?? ''));

            if ($serviceName === '') {
                $this->skipped++;

                continue;
            }

            $companyName = trim((string) ($row['شركة التأمين'] ?? $row['company'] ?? ''));
            $company = $companyName !== '' ? InsuranceCompany::where('name', $companyName)->first() : null;

            $service = Service::where('name', $serviceName)->first();

            if (! $service) {
                $this->skipped++;

                continue;
            }

            $priceList = PriceList::firstOrNew(['name' => $name]);

            if (! $priceList->exists) {
                $priceList->fill([
                    'type' => $row['النوع'] ?? $row['type'] ?? 'cash',
                    'ins_company_id' => $company?->id,
                    'ins_coverage' => (float) ($row['نسبة التغطية'] ?? $row['ins_coverage'] ?? 100),
                    'discount_pct' => (float) ($row['نسبة الخصم'] ?? $row['discount_pct'] ?? 0),
                    'is_active' => in_array(trim((string) ($row['الحالة'] ?? $row['is_active'] ?? 'active')), ['active', 'نشط', 'true', '1', 'yes'], true),
                ])->save();
                $this->created++;
            } else {
                $this->updated++;
            }

            $itemId = $priceList->items()->where('price', (float) ($row['السعر'] ?? $row['price'] ?? 0))->pluck('id')->first();

            $service = Service::where('name', $serviceName)->first();

            if ($itemId && $service) {
                $priceList->items()->whereKey($itemId)->update([
                    'service_id' => $service->id,
                    'price' => (float) ($row['السعر'] ?? $row['price'] ?? 0),
                ]);
            } else {
                $priceList->items()->create([
                    'service_id' => $service?->id,
                    'price' => (float) ($row['السعر'] ?? $row['price'] ?? 0),
                ]);
            }
        }
    }
}
