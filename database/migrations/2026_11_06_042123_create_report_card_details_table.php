<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_card_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->date('closing_date')->nullable();
            $table->date('next_term_begins')->nullable();
            $table->date('next_term_ends')->nullable();
            $table->unsignedSmallInteger('attendance')->nullable();
            $table->unsignedSmallInteger('attendance_out_of')->nullable();
            $table->string('conduct', 255)->nullable();
            $table->string('attitude', 255)->nullable();
            $table->text('class_teacher_remarks')->nullable();
            $table->text('head_teacher_remarks')->nullable();
            $table->decimal('school_fees_arrears', 10, 2)->nullable();
            $table->decimal('next_term_fee', 10, 2)->nullable();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['exam_id', 'student_id'], 'report_card_detail_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_card_details');
    }
};