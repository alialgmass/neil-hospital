<?php

namespace Database\Seeders\Historical;

use Database\Seeders\Historical\Concerns\NormalizesArabic;
use Illuminate\Database\Seeder;
use Modules\Doctor\Models\Doctor;

/**
 * Seeds the actual doctors that appear in the historical booking data.
 * Skips creation if a doctor with the same name already exists.
 */
class HistoricalDoctorsSeeder extends Seeder
{
    use NormalizesArabic;

    /** @var array<string, array<string, mixed>> */
    public const DOCTORS = [
        'عمرو الجارحى' => [
            'name' => 'د. عمرو الجارحى',
            'specialty' => 'جراحة المياه البيضاء والشبكية',
            'phone' => '01000000001',
            'fee_type' => 'fixed',
            'fee_value' => 4500.00,
            'dept_fees' => ['surgery' => ['type' => 'fixed', 'value' => 4500]],
            'departments' => ['surgery'],
            'is_active' => true,
            'notes' => 'جراح عمليات المياه البيضاء والشبكية',
        ],
        'عمر محمد حسن' => [
            'name' => 'د. عمر محمد حسن',
            'specialty' => 'جراحة العيون',
            'phone' => '01000000002',
            'fee_type' => 'fixed',
            'fee_value' => 4500.00,
            'dept_fees' => ['surgery' => ['type' => 'fixed', 'value' => 4500]],
            'departments' => ['surgery'],
            'is_active' => true,
            'notes' => 'جراح عمليات العيون',
        ],
        'خالد عبدالعظيم' => [
            'name' => 'د. خالد عبدالعظيم',
            'specialty' => 'جراحة العيون والشبكية',
            'phone' => '01000000003',
            'fee_type' => 'fixed',
            'fee_value' => 4500.00,
            'dept_fees' => ['surgery' => ['type' => 'fixed', 'value' => 4500]],
            'departments' => ['surgery'],
            'is_active' => true,
            'notes' => 'جراح عمليات المياه البيضاء والشبكية',
        ],
        'سحر محمد نشأت' => [
            'name' => 'د. سحر محمد نشأت',
            'specialty' => 'طب العيون العام',
            'phone' => '01000000004',
            'fee_type' => 'percentage',
            'fee_value' => 0.00,
            'dept_fees' => ['clinic' => ['type' => 'percentage', 'value' => 0]],
            'departments' => ['clinic'],
            'is_active' => true,
            'notes' => 'طبيبة عيادة',
        ],
        'رضوى سامى مالك' => [
            'name' => 'د. رضوى سامى مالك',
            'specialty' => 'طب العيون والأشعة',
            'phone' => '01000000005',
            'fee_type' => 'percentage',
            'fee_value' => 25.00,
            'dept_fees' => [
                'clinic' => ['type' => 'percentage', 'value' => 0],
                'labs' => ['type' => 'percentage', 'value' => 25],
            ],
            'departments' => ['clinic', 'labs'],
            'is_active' => true,
            'notes' => 'طبيبة عيادة وفحوصات أشعة',
        ],
        'حسناء حسين' => [
            'name' => 'د. حسناء حسين',
            'specialty' => 'الفحوصات والأشعة التشخيصية',
            'phone' => '01000000006',
            'fee_type' => 'percentage',
            'fee_value' => 25.00,
            'dept_fees' => ['labs' => ['type' => 'percentage', 'value' => 25]],
            'departments' => ['labs'],
            'is_active' => true,
            'notes' => 'طبيبة فحوصات وأشعة تشخيصية',
        ],
    ];

    public function run(): void
    {
        $existing = Doctor::pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [$this->normalizeArabic($name) => $id])
            ->toArray();

        foreach (self::DOCTORS as $key => $data) {
            $normalized = $this->normalizeArabic($data['name']);

            if (isset($existing[$normalized])) {
                $this->command->line("  – Skipped (exists): {$data['name']}");

                continue;
            }

            $doctor = Doctor::create($data);
            $existing[$normalized] = $doctor->id;
            $this->command->line("  ✓ Created doctor: {$data['name']}");
        }
    }
}
