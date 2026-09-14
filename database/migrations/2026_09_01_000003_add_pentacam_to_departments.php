<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Add the "البنتكام" (Pentacam) department:
     *  - widen the `dept` enum on services + bookings (MySQL only)
     *  - add its revenue account (4090) under operating revenue (4000)
     *
     * Pentacam charges are centre revenue only — no doctor-dues expense is
     * posted for them (see AutoPostDoctorDuesAction).
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE services MODIFY dept ENUM('clinic','labs','surgery','lasik','laser','pentacam') NOT NULL");
            DB::statement("ALTER TABLE bookings MODIFY dept ENUM('clinic','labs','surgery','lasik','laser','pentacam') NOT NULL");
        }

        if (DB::table('accounts')->where('code', '4090')->doesntExist()) {
            DB::table('accounts')->insert([
                'id' => (string) Str::ulid(),
                'code' => '4090',
                'name' => 'إيرادات وحدة البنتكام',
                'group' => 'revenues',
                'nature' => 'credit',
                'parent_id' => DB::table('accounts')->where('code', '4000')->value('id'),
                'balance' => 0,
                'is_active' => true,
                'is_postable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('accounts')->where('code', '4090')->delete();

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE services MODIFY dept ENUM('clinic','labs','surgery','lasik','laser') NOT NULL");
            DB::statement("ALTER TABLE bookings MODIFY dept ENUM('clinic','labs','surgery','lasik','laser') NOT NULL");
        }
    }
};
