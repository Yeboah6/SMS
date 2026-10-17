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
        Schema::create('exam_marks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('entered_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->decimal('marks_obtained', 8, 2)->nullable();
            $table->string('status', 20)->default('present');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['exam_subject_id', 'student_id'], 'exam_subject_student_mark_unique');
            $table->index(['student_id', 'exam_subject_id'], 'exam_student_result_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_marks');
    }
};
