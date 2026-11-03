<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentActivity;
use App\Models\User;

class StudentActivityService
{
    public function __construct(private AuditLogService $auditLogs) {}

    /**
     * @param  array<string, mixed>  $details
     */
    public function record(
        Student $student,
        ?User $actor,
        string $eventType,
        string $summary,
        ?AcademicYear $academicYear = null,
        array $details = [],
    ): StudentActivity {
        $activity = $student->activities()->create([
            'actor_id' => $actor?->id,
            'academic_year_id' => $academicYear?->id,
            'event_type' => $eventType,
            'summary' => $summary,
            'details' => $details,
            'occurred_at' => now(),
        ]);

        $this->auditLogs->recordStudentActivity($activity, $student, $actor);

        return $activity;
    }
}
