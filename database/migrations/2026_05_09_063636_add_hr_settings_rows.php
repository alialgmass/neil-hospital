<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['key' => 'hr_working_days',          'value' => '26',                                                                               'group' => 'hr'],
            ['key' => 'hr_hours_per_day',          'value' => '8',                                                                                'group' => 'hr'],
            ['key' => 'hr_overtime_multiplier',    'value' => '1.5',                                                                              'group' => 'hr'],
            ['key' => 'hr_late_deduction_pct',     'value' => '25',                                                                               'group' => 'hr'],
            ['key' => 'hr_half_day_deduction_pct', 'value' => '50',                                                                               'group' => 'hr'],
            ['key' => 'hr_departments',            'value' => 'العيادة,التمريض,الإدارة,المالية,المخزن,المختبر,الاستقبال,الصيانة,الأمن,الخدمات', 'group' => 'hr'],
        ];

        foreach ($rows as $row) {
            DB::table('settings')->insertOrIgnore(array_merge($row, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'hr_working_days',
            'hr_hours_per_day',
            'hr_overtime_multiplier',
            'hr_late_deduction_pct',
            'hr_half_day_deduction_pct',
            'hr_departments',
        ])->delete();
    }
};
