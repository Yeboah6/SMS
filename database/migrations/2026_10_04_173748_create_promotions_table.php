<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_enrolment_id')->unique()->constrained('enrolments')->restrictOnDelete();
            $table->foreignId('target_enrolment_id')->nullable()->unique()->constrained('enrolments')->restrictOnDelete();
            $table->foreignId('source_academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('target_academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('source_class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('source_section_id')->constrained('sections')->restrictOnDelete();
            $table->foreignId('target_class_id')->nullable()->constrained('classes')->restrictOnDelete();
            $table->foreignId('target_section_id')->nullable()->constrained('sections')->restrictOnDelete();
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->string('outcome', 20);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['source_academic_year_id', 'outcome'], 'promotions_source_year_outcome_index');
            $table->index(['target_academic_year_id', 'target_class_id', 'target_section_id'], 'promotions_target_placement_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
