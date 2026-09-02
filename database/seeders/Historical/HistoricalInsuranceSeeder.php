<?php

namespace Database\Seeders\Historical;

use Illuminate\Database\Seeder;
use Modules\Insurance\Models\InsuranceCompany;

/**
 * Seeds insurance companies that appear in the historical booking data.
 * Skips creation if a company with the same name already exists.
 */
class HistoricalInsuranceSeeder extends Seeder
{
    /** @var array<string, array<string, mixed>> */
    public const COMPANIES = [
        'التأمين الصحى' => [
            'name' => 'التأمين الصحى',
            'code' => 'INS-001',
            'phone' => null,
            'address' => null,
            'contract_no' => null,
            'coverage_pct' => 100.00,
            'disc_pct' => 0.00,
            'contact_person' => null,
            'email' => null,
            'status' => 'active',
            'notes' => 'التأمين الصحى الحكومى',
        ],
        'نفقة دولة' => [
            'name' => 'نفقة دولة',
            'code' => 'GOV-001',
            'phone' => null,
            'address' => null,
            'contract_no' => null,
            'coverage_pct' => 100.00,
            'disc_pct' => 0.00,
            'contact_person' => null,
            'email' => null,
            'status' => 'active',
            'notes' => 'نفقة الدولة للمرضى',
        ],
        'قافلة ضى الخير' => [
            'name' => 'قافلة ضى الخير',
            'code' => 'CHR-001',
            'phone' => null,
            'address' => null,
            'contract_no' => null,
            'coverage_pct' => 100.00,
            'disc_pct' => 0.00,
            'contact_person' => null,
            'email' => null,
            'status' => 'active',
            'notes' => 'قافلة خيرية',
        ],
    ];

    public function run(): void
    {
        foreach (self::COMPANIES as $key => $data) {
            $exists = InsuranceCompany::where('name', $data['name'])->exists();

            if (! $exists) {
                InsuranceCompany::create($data);
                $this->command->line("  ✓ Created insurance company: {$data['name']}");
            } else {
                $this->command->line("  – Skipped (exists): {$data['name']}");
            }
        }
    }
}
