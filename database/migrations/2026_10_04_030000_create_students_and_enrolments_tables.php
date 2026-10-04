<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('admission_number', 50)->unique();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->date('date_of_birth');
            $table->string('gender', 30);
            $table->string('blood_group', 5)->nullable();
            $table->string('religion', 100)->nullable();
            $table->text('address')->nullable();
            $table->string('photo_path')->nullable();
            $table->date('admission_date');
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['last_name', 'first_name'], 'students_name_index');
        });

        Schema::create('guardians', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 255);
            $table->string('phone', 50);
            $table->string('email')->nullable();
            $table->string('occupation', 150)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('phone', 'guardians_phone_index');
        });

        Schema::create('guardian_student', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained()->restrictOnDelete();
            $table->string('relationship', 50);
            $table->boolean('is_primary')->default(false);
            $table->boolean('lives_with_student')->default(false);
            $table->timestamps();
            $table->unique(['student_id', 'guardian_id'], 'guardian_student_unique');
            $table->index(['student_id', 'is_primary'], 'guardian_student_primary_index');
        });

        Schema::create('enrolments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->date('enrolment_date');
            $table->date('exit_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('outcome', 30)->nullable();
            $table->timestamps();
            $table->unique(['academic_year_id', 'student_id'], 'enrolments_year_student_unique');
            $table->index(['academic_year_id', 'class_id', 'section_id'], 'enrolments_year_class_section_index');
            $table->index(['student_id', 'academic_year_id'], 'enrolments_student_history_index');
        });

        Schema::create('student_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('category', 50);
            $table->string('original_name', 255);
            $table->string('storage_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
            $table->index(['student_id', 'category'], 'student_documents_category_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_documents');
        Schema::dropIfExists('enrolments');
        Schema::dropIfExists('guardian_student');
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('students');
    }
};
