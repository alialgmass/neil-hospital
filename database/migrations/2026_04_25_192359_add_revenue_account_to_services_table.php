<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->foreignUlid('revenue_account_id')->nullable()->after('status')
                ->constrained('accounts')->nullOnDelete();
        });

        // Insert new revenue accounts that didn't exist before
        $newAccounts = [
            '2500' => ['إيرادات الشبكية',            'revenues', 'credit'],
            '2600' => ['إيرادات التأمين الصحي',      'revenues', 'credit'],
            '2700' => ['إيرادات الأدوية والصيدلية',  'revenues', 'credit'],
        ];

        foreach ($newAccounts as $code => [$name, $group, $nature]) {
            DB::table('accounts')->updateOrInsert(
                ['code' => $code],
                [
                    'id' => (string) Str::ulid(),
                    'code' => $code,
                    'name' => $name,
                    'group' => $group,
                    'nature' => $nature,
                    'parent_id' => null,
                    'balance' => 0,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['revenue_account_id']);
            $table->dropColumn('revenue_account_id');
        });

        DB::table('accounts')
            ->whereIn('code', ['2500', '2600', '2700'])
            ->delete();
    }
};
