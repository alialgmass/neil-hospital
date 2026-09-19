<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NEW_NAME = 'مركز النيل الدولي للعيون';

    private const OLD_DEFAULTS = ['مستشفى النور', 'Laravel'];

    /**
     * Rename the centre. Only overwrites the stored value when it is still one
     * of the previous defaults, so a value the operator set by hand is kept.
     */
    public function up(): void
    {
        $current = DB::table('settings')->where('key', 'hospital_name')->value('value');

        if ($current === null) {
            DB::table('settings')->insert([
                'key' => 'hospital_name',
                'value' => self::NEW_NAME,
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        if (in_array($current, self::OLD_DEFAULTS, true)) {
            DB::table('settings')
                ->where('key', 'hospital_name')
                ->update(['value' => self::NEW_NAME, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'hospital_name')
            ->where('value', self::NEW_NAME)
            ->update(['value' => 'مستشفى النور', 'updated_at' => now()]);
    }
};
