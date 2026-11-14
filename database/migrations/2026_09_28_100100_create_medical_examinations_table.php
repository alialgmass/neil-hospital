<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL DDL is not transactional: an earlier failed run of this (still
        // unrecorded) migration may have left some of these tables behind.
        // They can only come from that failed run, so they hold no data.
        $this->dropTables();

        Schema::create('medical_examinations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();
            $table->foreignUlid('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->string('status', 20)->default('draft')->index();

            // Chief complaint
            $table->text('chief_complaint')->nullable();
            $table->string('complaint_duration', 100)->nullable();
            $table->string('affected_eye', 2)->nullable();

            // Ophthalmic history
            $table->json('eye_disease_history')->nullable();
            $table->text('eye_disease_notes')->nullable();
            $table->json('eye_surgery_history')->nullable();
            $table->text('eye_surgery_notes')->nullable();
            $table->boolean('eye_trauma')->nullable();
            $table->text('eye_trauma_notes')->nullable();
            $table->string('glasses_usage', 20)->nullable();
            $table->string('contact_lenses', 20)->nullable();
            $table->text('previous_eye_medications')->nullable();
            $table->text('allergies')->nullable();
            $table->json('systemic_diseases')->nullable();
            $table->text('history_notes')->nullable();

            // IOP measurement method (values live per eye)
            $table->string('iop_method', 20)->nullable();

            // Assessment & treatment
            $table->text('assessment')->nullable();
            $table->text('treatment_plan')->nullable();
            $table->json('medications')->nullable();
            $table->text('recommendations')->nullable();
            $table->string('follow_up', 255)->nullable();
            $table->date('next_visit_date')->nullable();

            $table->dateTime('examined_at');
            $table->dateTime('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('medical_examination_eyes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('medical_examination_id')->constrained('medical_examinations', 'id', 'med_exam_eyes_exam_fk')->cascadeOnDelete();
            $table->string('eye', 2);

            // Visual acuity
            $table->string('ucva', 20)->nullable();
            $table->string('cva', 20)->nullable();
            $table->string('near_vision', 20)->nullable();

            // Refraction
            $table->decimal('sphere', 5, 2)->nullable();
            $table->decimal('cylinder', 5, 2)->nullable();
            $table->unsignedSmallInteger('axis')->nullable();
            $table->decimal('add_power', 4, 2)->nullable();
            $table->string('bcva', 20)->nullable();

            // External / anterior segment
            $table->string('eyelids')->nullable();
            $table->string('conjunctiva')->nullable();
            $table->string('sclera')->nullable();
            $table->string('cornea')->nullable();
            $table->string('anterior_chamber')->nullable();
            $table->string('iris')->nullable();
            $table->string('pupil')->nullable();
            $table->string('lens')->nullable();

            // IOP
            $table->decimal('iop', 4, 1)->nullable();

            // Fundus
            $table->string('optic_disc')->nullable();
            $table->decimal('cd_ratio', 3, 2)->nullable();
            $table->string('macula')->nullable();
            $table->string('retina')->nullable();
            $table->string('vessels')->nullable();
            $table->string('vitreous')->nullable();

            $table->timestamps();

            $table->unique(['medical_examination_id', 'eye'], 'med_exam_eyes_exam_eye_unique');
        });

        Schema::create('medical_examination_diagnoses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('medical_examination_id')->constrained('medical_examinations', 'id', 'med_exam_diag_exam_fk')->cascadeOnDelete();
            $table->foreignUlid('diagnosis_id')->constrained('diagnoses', 'id', 'med_exam_diag_diagnosis_fk')->restrictOnDelete();
            $table->string('eye', 2)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('medical_examination_investigations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('medical_examination_id')->constrained('medical_examinations', 'id', 'med_exam_inv_exam_fk')->cascadeOnDelete();
            $table->foreignUlid('service_id')->nullable()->constrained('services', 'id', 'med_exam_inv_service_fk')->nullOnDelete();
            $table->string('name', 150);
            $table->string('eye', 2)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->dropTables();
    }

    private function dropTables(): void
    {
        Schema::dropIfExists('medical_examination_investigations');
        Schema::dropIfExists('medical_examination_diagnoses');
        Schema::dropIfExists('medical_examination_eyes');
        Schema::dropIfExists('medical_examinations');
    }
};
