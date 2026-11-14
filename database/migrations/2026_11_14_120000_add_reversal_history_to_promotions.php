<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table): void {
            $table->dropUnique('promotions_source_enrolment_id_unique');
            $table->dropUnique('promotions_target_enrolment_id_unique');
            $table->index(['source_enrolment_id', 'id'], 'promotions_source_enrolment_history_index');
            $table->index(['target_enrolment_id', 'id'], 'promotions_target_enrolment_history_index');
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
        });
    }

    public function down(): void
    {
        $hasRepeatedSourceEnrolments = DB::table('promotions')
            ->select('source_enrolment_id')
            ->groupBy('source_enrolment_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasRepeatedSourceEnrolments) {
            throw new RuntimeException(
                'Cannot restore unique source-enrolment promotion records while multiple decisions reference the same source enrolment.',
            );
        }

        $hasRepeatedTargetEnrolments = DB::table('promotions')
            ->whereNotNull('target_enrolment_id')
            ->select('target_enrolment_id')
            ->groupBy('target_enrolment_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasRepeatedTargetEnrolments) {
            throw new RuntimeException(
                'Cannot restore unique target-enrolment promotion records while multiple decisions reference the same target enrolment.',
            );
        }

        Schema::table('promotions', function (Blueprint $table): void {
            $table->dropForeign(['reversed_by']);
            $table->dropIndex('promotions_source_enrolment_history_index');
            $table->dropIndex('promotions_target_enrolment_history_index');
            $table->unique('source_enrolment_id', 'promotions_source_enrolment_id_unique');
            $table->unique('target_enrolment_id', 'promotions_target_enrolment_id_unique');
            $table->dropColumn(['reversed_at', 'reversed_by', 'reversal_reason']);
        });
    }
};
