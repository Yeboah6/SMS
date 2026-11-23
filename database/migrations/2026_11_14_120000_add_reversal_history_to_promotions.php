<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Columns (guarded, because the earlier failed run may already have added them).
        if (! Schema::hasColumn('promotions', 'reversed_at')) {
            Schema::table('promotions', fn (Blueprint $table) => $table->timestamp('reversed_at')->nullable());
        }
        if (! Schema::hasColumn('promotions', 'reversed_by')) {
            Schema::table('promotions', fn (Blueprint $table) => $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete());
        }
        if (! Schema::hasColumn('promotions', 'reversal_reason')) {
            Schema::table('promotions', fn (Blueprint $table) => $table->text('reversal_reason')->nullable());
        }

        // 2. Replacement indexes FIRST, so the enrolment FKs always have an index.
        if (! Schema::hasIndex('promotions', 'promotions_source_enrolment_history_index')) {
            Schema::table('promotions', fn (Blueprint $table) => $table->index(['source_enrolment_id', 'id'], 'promotions_source_enrolment_history_index'));
        }
        if (! Schema::hasIndex('promotions', 'promotions_target_enrolment_history_index')) {
            Schema::table('promotions', fn (Blueprint $table) => $table->index(['target_enrolment_id', 'id'], 'promotions_target_enrolment_history_index'));
        }

        // 3. Only now drop the old unique indexes.
        if (Schema::hasIndex('promotions', 'promotions_source_enrolment_id_unique')) {
            Schema::table('promotions', fn (Blueprint $table) => $table->dropUnique('promotions_source_enrolment_id_unique'));
        }
        if (Schema::hasIndex('promotions', 'promotions_target_enrolment_id_unique')) {
            Schema::table('promotions', fn (Blueprint $table) => $table->dropUnique('promotions_target_enrolment_id_unique'));
        }
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

        // Restore the uniques before removing the indexes the FKs are using.
        Schema::table('promotions', function (Blueprint $table): void {
            $table->unique('source_enrolment_id', 'promotions_source_enrolment_id_unique');
            $table->unique('target_enrolment_id', 'promotions_target_enrolment_id_unique');
        });

        Schema::table('promotions', function (Blueprint $table): void {
            $table->dropIndex('promotions_source_enrolment_history_index');
            $table->dropIndex('promotions_target_enrolment_history_index');
            $table->dropForeign(['reversed_by']);
            $table->dropColumn(['reversed_at', 'reversed_by', 'reversal_reason']);
        });
    }
};