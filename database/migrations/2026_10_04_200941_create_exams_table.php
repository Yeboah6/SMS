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
        Schema::create('exams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('exam_type', 30);
            $table->string('status', 20)->default('draft');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->timestamps();

            $table->unique(['academic_year_id', 'class_id', 'name'], 'exam_year_class_name_unique');
            $table->index(['academic_year_id', 'class_id', 'status'], 'exam_year_class_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
