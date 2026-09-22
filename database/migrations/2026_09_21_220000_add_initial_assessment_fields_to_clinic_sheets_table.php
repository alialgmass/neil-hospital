<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_sheets', function (Blueprint $table) {
            // Initial Medical Assessment
            $table->string('patient_contact', 30)->nullable();
            $table->enum('allergies_status', ['none', 'yes'])->nullable();
            $table->string('allergies_specify', 500)->nullable();
            $table->json('current_medications')->nullable();
            $table->json('plan_medications')->nullable();
            $table->json('visual_exam')->nullable();
            $table->json('eye_exam_grid')->nullable();
            $table->boolean('plan_education')->nullable();
            $table->text('plan_followup')->nullable();

            // Initial Nursing Assessment
            $table->json('nursing_assessment')->nullable();
            $table->json('nursing_history_answers')->nullable();
            $table->json('fall_screening')->nullable();
            $table->unsignedTinyInteger('fall_screening_score')->nullable();
            $table->json('drops_given')->nullable();
            $table->json('critical_results')->nullable();
            $table->text('nursing_notes')->nullable();
            $table->string('nurse_signature_name', 150)->nullable();
            $table->string('evaluator_name', 150)->nullable();
            $table->dateTime('evaluated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clinic_sheets', function (Blueprint $table) {
            $table->dropColumn([
                'patient_contact', 'allergies_status', 'allergies_specify',
                'current_medications', 'plan_medications', 'visual_exam', 'eye_exam_grid',
                'plan_education', 'plan_followup',
                'nursing_assessment', 'nursing_history_answers', 'fall_screening', 'fall_screening_score',
                'drops_given', 'critical_results', 'nursing_notes',
                'nurse_signature_name', 'evaluator_name', 'evaluated_at',
            ]);
        });
    }
};
