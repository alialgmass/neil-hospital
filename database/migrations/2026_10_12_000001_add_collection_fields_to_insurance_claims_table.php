<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_claims', function (Blueprint $table) {
            $table->decimal('deduction', 10, 2)->default(0)->after('paid_amount');
            $table->text('deduction_reason')->nullable()->after('deduction');
            $table->decimal('rejected_amount', 10, 2)->default(0)->after('deduction_reason');
            $table->foreignId('collected_by')->nullable()->after('rejected_amount')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('collected_at')->nullable()->after('collected_by');
        });
    }

    public function down(): void
    {
        Schema::table('insurance_claims', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collected_by');
            $table->dropColumn(['deduction', 'deduction_reason', 'rejected_amount', 'collected_at']);
        });
    }
};
