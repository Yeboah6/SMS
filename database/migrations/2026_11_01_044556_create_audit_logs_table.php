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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->string('action', 120);
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_name')->nullable();
            $table->string('summary');
            $table->json('changed_fields')->nullable();
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('route_name')->nullable();
            $table->string('source_type', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['occurred_at', 'id']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_id', 'occurred_at']);
            $table->index(['action', 'occurred_at']);
            $table->unique(['source_type', 'source_id'], 'audit_logs_source_unique');
        });

        if (Schema::hasTable('student_activities')) {
            DB::table('student_activities')
                ->orderBy('id')
                ->chunkById(500, function ($activities): void {
                    $actorNames = DB::table('users')
                        ->whereIn('id', $activities->pluck('actor_id')->filter()->unique())
                        ->pluck('name', 'id');
                    $studentNames = DB::table('students')
                        ->whereIn('id', $activities->pluck('student_id')->unique())
                        ->get(['id', 'first_name', 'middle_name', 'last_name'])
                        ->mapWithKeys(fn (object $student): array => [
                            $student->id => trim(implode(' ', array_filter([
                                $student->first_name,
                                $student->middle_name,
                                $student->last_name,
                            ]))),
                        ]);

                    $rows = $activities->map(fn (object $activity): array => [
                        'actor_id' => $activity->actor_id,
                        'actor_name' => $activity->actor_id === null
                            ? 'System'
                            : ($actorNames->get($activity->actor_id) ?? 'System'),
                        'academic_year_id' => $activity->academic_year_id,
                        'action' => $activity->event_type,
                        'subject_type' => 'App\\Models\\Student',
                        'subject_id' => $activity->student_id,
                        'subject_name' => $studentNames->get($activity->student_id)
                            ?: 'Student #'.$activity->student_id,
                        'summary' => $activity->summary,
                        'changed_fields' => null,
                        'context' => $activity->details,
                        'ip_address' => null,
                        'route_name' => null,
                        'source_type' => 'student_activity',
                        'source_id' => $activity->id,
                        'occurred_at' => $activity->occurred_at,
                        'created_at' => $activity->created_at,
                        'updated_at' => $activity->updated_at,
                    ])->all();

                    DB::table('audit_logs')->insertOrIgnore($rows);
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
