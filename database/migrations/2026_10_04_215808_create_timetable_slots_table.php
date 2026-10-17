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
        Schema::create('timetable_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_subject_assignment_id')
                ->constrained('teacher_subject_assignments')
                ->restrictOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('room', 100)->nullable();
            $table->timestamps();

            $table->index(['academic_year_id', 'day_of_week', 'starts_at'], 'timetable_year_day_start_index');
            $table->index(
                ['teacher_subject_assignment_id', 'day_of_week', 'starts_at'],
                'timetable_assignment_day_start_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetable_slots');
    }
};
