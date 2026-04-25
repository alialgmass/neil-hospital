<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    private const DEFAULTS = [
        // key                       => [value, group]
        'hospital_name' => ['مستشفى النور', 'general'],
        'hospital_specialty' => ['طب وجراحة العيون', 'general'],
        'hospital_address' => ['المنيا، مصر', 'general'],
        'mrn_format' => ['MRN-{year}-{seq5}', 'booking'],
        'low_stock_alerts_enabled' => ['true', 'inventory'],
        'default_currency' => ['EGP', 'finance'],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $key => [$value, $group]) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                [
                    'key' => $key,
                    'value' => $value,
                    'group' => $group,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }
}
