<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Admin\Enums\SystemModule;

return new class extends Migration
{
    public function up(): void
    {
        foreach (SystemModule::cases() as $module) {
            DB::table('settings')->insertOrIgnore([
                'key' => $module->settingKey(),
                'value' => 'true',
                'group' => 'modules',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn(
            'key',
            array_map(fn (SystemModule $module) => $module->settingKey(), SystemModule::cases()),
        )->delete();
    }
};
