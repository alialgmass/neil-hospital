<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Doctor\Models\Doctor;

class DoctorSeeder extends Seeder
{
    public function run(): void
    {
        $doctors = [
            [
                'name' => 'د. أحمد محمد',
                'specialty' => 'القرنية والقطاعات الأمامية',
                'phone' => '01012345671',
                'fee_type' => 'percentage',
                'fee_value' => 25.00,

                'is_active' => true,
                'notes' => 'أخصائي القرنية والمقلة',
            ],
            [
                'name' => 'د. خالد ياسين',
                'specialty' => 'الشبكية والزجاجي',
                'phone' => '01012345672',
                'fee_type' => 'percentage',
                'fee_value' => 30.00,

                'is_active' => true,
                'notes' => 'أخصائي الشبكية والجرعة الزجاجية',
            ],
            [
                'name' => 'د. عمر فريد',
                'specialty' => 'جراحة المياه البيضاء',
                'phone' => '01012345673',
                'fee_type' => 'percentage',
                'fee_value' => 25.00,

                'is_active' => true,
                'notes' => 'متخصص في إزالة المياه البيضاء',
            ],
            [
                'name' => 'د. سارة أحمد',
                'specialty' => 'الليزك وتصحيح البصر',
                'phone' => '01012345674',
                'fee_type' => 'percentage',
                'fee_value' => 30.00,

                'is_active' => true,
                'notes' => 'أخصائية الليزك وتصحيح البصر بالليزر',
            ],
            [
                'name' => 'د. محمد علي',
                'specialty' => 'الليزر الطبي',
                'phone' => '01012345675',
                'fee_type' => 'percentage',
                'fee_value' => 25.00,

                'is_active' => true,
                'notes' => 'متخصص العلاجات بالليزر',
            ],
            [
                'name' => 'د. ليلى محمود',
                'specialty' => 'الحول والعيون للأطفال',
                'phone' => '01012345676',
                'fee_type' => 'percentage',
                'fee_value' => 20.00,

                'is_active' => true,
                'notes' => 'متخصصة في طب عيون الأطفال',
            ],
        ];

        foreach ($doctors as $doctor) {
            Doctor::create($doctor);
        }
    }
}
