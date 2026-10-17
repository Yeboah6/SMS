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
        Schema::create('admissions', function (Blueprint $table): void {
            $table->id();
            $table->string('application_number', 20)->nullable()->unique();
            $table->string('parent_name', 255);
            $table->string('parent_phone', 50);
            $table->string('parent_email')->nullable();
            $table->string('parent_occupation', 150)->nullable();
            $table->text('parent_address')->nullable();
            $table->string('child_first_name', 100);
            $table->string('child_middle_name', 100)->nullable();
            $table->string('child_last_name', 100);
            $table->date('date_of_birth');
            $table->string('gender', 30);
            $table->text('child_address')->nullable();
            $table->string('applying_for', 100);
            $table->string('source', 30)->index();
            $table->string('status', 30)->default('enquiry')->index();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'created_at'], 'admissions_status_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admissions');
    }
};
