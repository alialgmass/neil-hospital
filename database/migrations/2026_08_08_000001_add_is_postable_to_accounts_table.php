<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Enums\AccountCode;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('accounts', 'is_postable')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->boolean('is_postable')->default(true)->after('is_active');
            });
        }

        // Parent/summary accounts are never postable.
        DB::table('accounts')->whereIn('code', AccountCode::nonPostableCodes())->update(['is_postable' => false]);

        // Defense in depth: anything that has children is a summary account too,
        // regardless of whether it's in the known list above. MySQL forbids
        // selecting from the same table being updated, so wrap in a derived table.
        $parentIds = DB::table('accounts as parents')
            ->join('accounts as children', 'children.parent_id', '=', 'parents.id')
            ->select('parents.id')
            ->distinct()
            ->pluck('id');

        if ($parentIds->isNotEmpty()) {
            DB::table('accounts')->whereIn('id', $parentIds)->update(['is_postable' => false]);
        }
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('is_postable');
        });
    }
};
