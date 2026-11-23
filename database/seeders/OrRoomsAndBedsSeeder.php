<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrRoomsAndBedsSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [
            ['name' => 'غرفة 1', 'total_beds' => 10],
            ['name' => 'غرفة 2', 'total_beds' => 10],
            ['name' => 'غرفة 3', 'total_beds' => 10],
            ['name' => 'غرفة 4', 'total_beds' => 10],
            ['name' => 'غرفة 5', 'total_beds' => 10],
            ['name' => 'غرفة 6', 'total_beds' => 10],
        ];

        foreach ($rooms as $room) {
            $roomId = DB::table('or_rooms')->insertGetId([
                'name' => $room['name'],
                'status' => 'available',
                'total_beds' => $room['total_beds'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            for ($i = 1; $i <= $room['total_beds']; $i++) {
                DB::table('or_beds')->insert([
                    'room_id' => $roomId,
                    'bed_number' => (string) $i,
                    'status' => 'available',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
