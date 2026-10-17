<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->date('attendance_date');
            $table->foreignId('marked_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(
                ['academic_year_id', 'section_id', 'attendance_date'],
                'attendance_year_section_date_unique',
            );
        });

        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendance')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrolment_id')->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['attendance_id', 'student_id'], 'attendance_student_record_unique');
            $table->index(['student_id', 'attendance_id'], 'attendance_records_student_event_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance');
    }
};
