<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->dropForeign(['staff_id']);
            $table->unsignedBigInteger('staff_id')->nullable()->change();
            $table->foreignId('student_id')->nullable()->after('staff_id')->constrained()->restrictOnDelete();
            $table->foreign('staff_id')->references('id')->on('staff')->restrictOnDelete();
            $table->index(['student_id', 'status', 'start_date', 'end_date'], 'leave_student_status_dates_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('leave_requests')->whereNull('staff_id')->exists()) {
            throw new RuntimeException('Student leave requests exist; migrate or archive them before rolling back.');
        }

        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->dropIndex('leave_student_status_dates_index');
            $table->dropForeign(['student_id']);
            $table->dropColumn('student_id');
            $table->dropForeign(['staff_id']);
            $table->unsignedBigInteger('staff_id')->nullable(false)->change();
            $table->foreign('staff_id')->references('id')->on('staff')->restrictOnDelete();
        });
    }
};
