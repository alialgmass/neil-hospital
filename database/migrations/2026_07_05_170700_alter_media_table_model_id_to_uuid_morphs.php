<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasIndex('media', ['model_type', 'model_id'])) {
            Schema::table('media', function (Blueprint $table) {
                $table->dropIndex('media_model_type_model_id_index');
            });
        }

        Schema::table('media', function (Blueprint $table) {
            $table->string('model_type', 191)->change();
            $table->uuid('model_id')->change();
            $table->index(['model_type', 'model_id'], 'media_model_type_model_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex('media', ['model_type', 'model_id'])) {
            Schema::table('media', function (Blueprint $table) {
                $table->dropIndex('media_model_type_model_id_index');
            });
        }

        Schema::table('media', function (Blueprint $table) {
            $table->unsignedBigInteger('model_id')->change();
            if (Schema::hasIndex('media', ['model_type', 'model_id'])) {
                $table->index(['model_type', 'model_id'], 'media_model_type_model_id_index');
            }
        });
    }
};
