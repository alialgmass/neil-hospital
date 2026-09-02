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
