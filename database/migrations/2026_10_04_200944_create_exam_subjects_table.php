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
        Schema::create('exam_subjects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->decimal('max_marks', 8, 2);
            $table->decimal('pass_marks', 8, 2);
            $table->date('exam_date');
            $table->timestamps();

            $table->unique(['exam_id', 'subject_id'], 'exam_subject_unique');
            $table->index(['subject_id', 'exam_id'], 'exam_subject_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_subjects');
    }
};
