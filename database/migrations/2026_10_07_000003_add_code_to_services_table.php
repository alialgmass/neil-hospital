<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PREFIXES = [
        'clinic' => 'CLN',
        'labs' => 'LAB',
        'surgery' => 'SRG',
        'lasik' => 'LSK',
        'laser' => 'LSR',
        'pentacam' => 'PNT',
    ];

    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('code', 30)->nullable()->unique()->after('id');
        });

        // Backfill existing services: <DEPT>-0001, numbered per department by creation order.
        $counters = [];

        DB::table('services')->orderBy('created_at')->orderBy('id')->get(['id', 'dept'])->each(function ($service) use (&$counters) {
            $prefix = self::PREFIXES[$service->dept] ?? strtoupper(substr((string) $service->dept, 0, 3));
            $counters[$prefix] = ($counters[$prefix] ?? 0) + 1;

            DB::table('services')->where('id', $service->id)->update([
                'code' => sprintf('%s-%04d', $prefix, $counters[$prefix]),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
