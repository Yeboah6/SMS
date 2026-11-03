<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentActivity;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AuditLogService
{
    /**
     * @param  list<string>  $fields
     */
    public function recordModelChange(Model $model, string $action, array $fields): void
    {
        $actor = Auth::user();

        $this->write($model, $action, $fields, $actor instanceof User ? $actor : null);
    }

    /**
     * @param  list<string>  $fields
     */
    public function recordRelationshipChange(
        Model $model,
        string $action,
        array $fields,
        ?User $actor,
    ): void {
        $currentActor = Auth::user();

        $this->write(
            $model,
            $action,
            $fields,
            $actor ?? ($currentActor instanceof User ? $currentActor : null),
        );
    }

    public function recordStudentActivity(
        StudentActivity $activity,
        Student $student,
        ?User $actor,
    ): void {
        $this->write(
            $student,
            $activity->event_type,
            [],
            $actor,
            $activity->details,
            $activity->summary,
            'student_activity',
            $activity->id,
            $activity->academic_year_id,
            $activity->occurred_at,
        );
    }

    /**
     * @param  list<string>  $fields
     * @param  array<string, mixed>|null  $context
     */
    private function write(
        Model $subject,
        string $action,
        array $fields,
        ?User $actor,
        ?array $context = null,
        ?string $summary = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?int $academicYearId = null,
        mixed $occurredAt = null,
    ): void {
        $changedFields = collect($fields)
            ->filter(fn (mixed $field): bool => is_string($field))
            ->reject(fn (string $field): bool => in_array($field, [
                'id',
                'created_at',
                'updated_at',
                'remember_token',
            ], true))
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($action === 'updated' && $changedFields === []) {
            return;
        }

        $subjectName = $this->subjectName($subject);

        AuditLog::query()->create([
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name ?? 'System',
            'academic_year_id' => $academicYearId ?? $subject->getAttribute('academic_year_id'),
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'subject_name' => $subjectName,
            'summary' => $summary ?? Str::limit(
                Str::headline(str_replace('.', ' ', $action)).' '.class_basename($subject).': '.$subjectName,
                255,
            ),
            'changed_fields' => $changedFields,
            'context' => $context,
            'ip_address' => request()->ip(),
            'route_name' => request()->route()?->getName(),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }

    private function subjectName(Model $subject): string
    {
        if ($subject instanceof Student) {
            $name = trim(implode(' ', array_filter([
                $subject->first_name,
                $subject->middle_name,
                $subject->last_name,
            ])));

            return $name !== '' ? $name : 'Student #'.$subject->getKey();
        }

        if ($subject instanceof SchoolSetting) {
            return $subject->school_name ?: 'School settings';
        }

        if ($subject instanceof Staff || $subject instanceof User) {
            return $subject->name ?: class_basename($subject).' #'.$subject->getKey();
        }

        if (
            $subject instanceof AcademicYear
            || $subject instanceof Exam
            || $subject instanceof Permission
            || $subject instanceof Role
            || $subject instanceof SchoolClass
            || $subject instanceof Section
            || $subject instanceof Subject
        ) {
            return $subject->name ?: class_basename($subject).' #'.$subject->getKey();
        }

        return class_basename($subject).' #'.$subject->getKey();
    }
}
