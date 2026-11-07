<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ضريبة الخصم والإضافة — كل شركة تأمين تخصم نسبة مختلفة من مدفوعاتها وترسلها
 * للضرائب مباشرة. لا نسبة افتراضية يفرضها النظام: تبقى null حتى يُدخلها
 * الإداري لكل شركة على حدة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_companies', function (Blueprint $table) {
            $table->decimal('withholding_pct', 5, 2)->nullable()->after('disc_pct');
        });
    }

    public function down(): void
    {
        Schema::table('insurance_companies', function (Blueprint $table) {
            $table->dropColumn('withholding_pct');
        });
    }
};
