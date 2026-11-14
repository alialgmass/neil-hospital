<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_bookings', function (Blueprint $table) {
            $table->ulid('ins_company_id')->nullable()->after('doctor_id');

            $table->foreign('ins_company_id', 'pre_bookings_ins_company_fk')
                ->references('id')->on('insurance_companies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pre_bookings', function (Blueprint $table) {
            $table->dropForeign('pre_bookings_ins_company_fk');
            $table->dropColumn('ins_company_id');
        });
    }
};
