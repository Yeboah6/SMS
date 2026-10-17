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
        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_id')->constrained()->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('leave_type', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comments')->nullable();
            $table->timestamps();

            $table->index(['staff_id', 'status', 'start_date', 'end_date'], 'leave_staff_status_dates_index');
            $table->index(['status', 'created_at'], 'leave_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
