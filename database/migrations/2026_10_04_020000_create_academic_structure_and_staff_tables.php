<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('designations', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('staff', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('staff_number', 50)->unique();
            $table->boolean('is_teacher')->default(false)->index();
            $table->string('name', 255);
            $table->string('email')->nullable()->index();
            $table->string('phone', 50)->nullable();
            $table->date('joining_date')->nullable();
            $table->text('qualifications')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('classes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['academic_year_id', 'name']);
            $table->index(['academic_year_id', 'sort_order']);
        });

        Schema::create('sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->timestamps();
            $table->unique(['class_id', 'name']);
            $table->index(['academic_year_id', 'class_id']);
        });

        Schema::create('subjects', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('class_subjects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['class_id', 'subject_id']);
            $table->index(['academic_year_id', 'class_id']);
        });

        Schema::create('teacher_class_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['academic_year_id', 'staff_id', 'section_id'], 'teacher_class_year_staff_section_unique');
            $table->index(['academic_year_id', 'section_id']);
        });

        Schema::create('teacher_subject_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(
                ['academic_year_id', 'staff_id', 'section_id', 'subject_id'],
                'teacher_subject_year_staff_section_subject_unique',
            );
            $table->index(['academic_year_id', 'section_id', 'subject_id'], 'teacher_subject_year_section_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_subject_assignments');
        Schema::dropIfExists('teacher_class_assignments');
        Schema::dropIfExists('class_subjects');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('designations');
        Schema::dropIfExists('departments');
    }
};
