<?php

namespace Database\Seeders\Historical;

use Illuminate\Database\Seeder;

/**
 * Main orchestrator for all historical data seeders.
 *
 * Run with:
 *   php artisan db:seed --class="Database\\Seeders\\Historical\\HistoricalSeeder"
 *
 * Order matters:
 *   1. Doctors      — referenced by bookings
 *   2. Insurance    — referenced by surgery bookings & claims
 *   3. Surgery      — creates bookings + insurance claims (dept: surgery)
 *   4. Clinic       — creates bookings (dept: clinic)
 *   5. Labs         — creates bookings for pentacam + radiology (dept: labs)
 *
 * Doctors and services: each booking row carries a doctor and a service name
 * from the source sheet (جهار 1 (1).xlsx). ResolvesHistoricalLinks matches
 * those against real doctors/services (spelling-tolerant) and creates any that
 * are missing, so no booking is left with a null/invalid doctor_id or
 * service_id — services do not need to be seeded beforehand.
 *
 * Accounting: every booking's entries are posted through the AutoPost* actions
 * (never a hardcoded account id/code), so this data automatically tracks the
 * chart of accounts described by Modules\Accounting\Enums\AccountCode — currently
 * الدليل المحاسبي v2.0. Coverage guarded by
 * tests/Feature/Accounting/HistoricalSeederAccountingTest.php (doctor/service
 * linkage, ledger foots, no post to a non-postable account).
 */
class HistoricalSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('▶ Historical data seeding started');

        $this->command->info('  → Doctors');
        $this->call(HistoricalDoctorsSeeder::class);

        $this->command->info('  → Insurance companies');
        $this->call(HistoricalInsuranceSeeder::class);

        $this->command->info('  → Surgery bookings (عمليات)');
        $this->call(HistoricalSurgeryBookingsSeeder::class);

        $this->command->info('  → Clinic bookings (كشف)');
        $this->call(HistoricalClinicBookingsSeeder::class);

        $this->command->info('  → Labs bookings (بنتكام + اشاعات)');
        $this->call(HistoricalLabsBookingsSeeder::class);

        $this->command->info('✅ Historical data seeding complete');
    }
}
