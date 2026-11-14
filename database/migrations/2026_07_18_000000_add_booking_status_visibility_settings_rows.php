<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Booking\States\BookingStatus;

return new class extends Migration
{
    public function up(): void
    {
        foreach (BookingStatus::options() as $option) {
            DB::table('settings')->insertOrIgnore([
                'key' => BookingStatus::settingKey($option['value']),
                'value' => 'true',
                'group' => 'booking_statuses',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn(
            'key',
            array_map(
                fn (array $option) => BookingStatus::settingKey($option['value']),
                BookingStatus::options(),
            ),
        )->delete();
    }
};
