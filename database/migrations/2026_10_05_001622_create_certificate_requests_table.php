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
        Schema::create('certificate_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('certificate_type', 30);
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->string('certificate_number', 40)->nullable()->unique();
            $table->string('school_name')->nullable();
            $table->string('student_name')->nullable();
            $table->string('admission_number', 50)->nullable();
            $table->text('review_comments')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status', 'created_at'], 'certificate_student_status_created_index');
            $table->index(['status', 'created_at'], 'certificate_status_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_requests');
    }
};
