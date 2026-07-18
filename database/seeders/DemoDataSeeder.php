<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Booking\Models\Booking;
use Modules\Doctor\Models\Doctor;
use Modules\Inventory\Models\InventoryItem;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Clear existing data
        DB::table('bookings')->truncate();
        DB::table('dr_payments')->truncate();
        DB::table('inventory')->truncate();

        // 2. Create Doctors (if not existing)
        $doctors = [
            ['name' => 'د. أحمد علي', 'specialty' => 'جراحة الشبكية', 'fee_type' => 'percentage', 'fee_value' => 20],
            ['name' => 'د. سارة محمود', 'specialty' => 'المياه البيضاء', 'fee_type' => 'fixed', 'fee_value' => 150],
            ['name' => 'د. خالد يوسف', 'specialty' => 'تصحيح الإبصار', 'fee_type' => 'percentage', 'fee_value' => 15],
        ];

        foreach ($doctors as $dr) {
            Doctor::updateOrCreate(['name' => $dr['name']], [
                'id' => (string) Str::ulid(),
                'specialty' => $dr['specialty'],
                'fee_type' => $dr['fee_type'],
                'fee_value' => $dr['fee_value'],
                'is_active' => true,
            ]);
        }

        $allDoctors = Doctor::all();

        // 3. Create Inventory Items (including low stock)
        $items = [
            ['name' => 'قطرة معقمة', 'quantity' => 50, 'min_quantity' => 10],
            ['name' => 'عدسات لاصقة', 'quantity' => 5, 'min_quantity' => 15], // Low Stock
            ['name' => 'مشرط جراحي', 'quantity' => 100, 'min_quantity' => 20],
            ['name' => 'جهاز فحص قرنية', 'quantity' => 2, 'min_quantity' => 1],
            ['name' => 'شاش طبي', 'quantity' => 3, 'min_quantity' => 10], // Low Stock
        ];

        foreach ($items as $item) {
            InventoryItem::create(array_merge($item, [
                'id' => (string) Str::ulid(),
                'code' => 'INV-'.rand(1000, 9999),
                'category' => 'medical',
                'unit' => 'قطعة',
                'unit_cost' => rand(10, 500),
            ]));
        }

        // 4. Create Bookings (Mixture of states)
        for ($i = 0; $i < 20; $i++) {
            $date = now()->subDays(rand(0, 5));
            $isOldUnpaid = $i < 3; // First 3 are old unpaid for alerts

            Booking::create([
                'id' => (string) Str::ulid(),
                'file_no' => 'F-'.(1000 + $i),
                'patient_name' => 'مريض رقم '.($i + 1),
                'patient_phone' => '0100'.rand(1000000, 9999999),
                'dept' => collect(['clinic', 'surgery', 'lasik'])->random(),
                'service_name' => 'كشف عيون شامل',
                'doctor_id' => $allDoctors->random()->id,
                'visit_date' => $isOldUnpaid ? now()->subDays(2) : $date,
                'price' => rand(200, 2000),
                'pay_status' => $isOldUnpaid ? 'unpaid' : collect(['paid', 'paid', 'unpaid'])->random(),
                'status' => collect(['confirmed', 'completed', 'waiting', 'confirmed'])->random(),
                'created_at' => $date,
            ]);
        }
    }
}
