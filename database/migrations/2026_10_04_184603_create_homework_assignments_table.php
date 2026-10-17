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
        Schema::create('homework_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 255);
            $table->text('instructions');
            $table->date('assigned_date');
            $table->date('due_date');
            $table->decimal('max_points', 8, 2)->default(100);
            $table->timestamps();

            $table->index(['academic_year_id', 'section_id', 'due_date'], 'homework_year_section_due_index');
            $table->index(['section_id', 'subject_id', 'academic_year_id'], 'homework_scope_index');
        });

        Schema::create('homework_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('homework_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->text('content');
            $table->timestamp('submitted_at');
            $table->decimal('score', 8, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['homework_assignment_id', 'student_id'], 'homework_submission_student_unique');
            $table->index(['student_id', 'submitted_at'], 'homework_student_submitted_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('homework_submissions');
        Schema::dropIfExists('homework_assignments');
    }
};
