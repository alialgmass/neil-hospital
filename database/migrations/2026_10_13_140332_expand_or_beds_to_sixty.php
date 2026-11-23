<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const BEDS_PER_ROOM = 10;

    /**
     * Raise every operating room to 10 beds (6 rooms = 60 beds).
     */
    public function up(): void
    {
        DB::table('or_rooms')->get()->each(function (object $room): void {
            $existing = DB::table('or_beds')->where('room_id', $room->id)->count();

            for ($i = $existing + 1; $i <= self::BEDS_PER_ROOM; $i++) {
                DB::table('or_beds')->insert([
                    'room_id' => $room->id,
                    'bed_number' => (string) $i,
                    'status' => 'available',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('or_rooms')->where('id', $room->id)->update(['total_beds' => max($existing, self::BEDS_PER_ROOM)]);
        });

        DB::table('settings')->where('key', 'surgery_beds')->update(['value' => '60']);
    }

    public function down(): void
    {
        // Data expansion only; beds may already be assigned to surgeries.
    }
};
