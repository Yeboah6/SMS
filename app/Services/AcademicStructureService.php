<?php

namespace App\Services;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Subject;
use App\Models\TeacherClassAssignment;
use App\Models\TeacherSubjectAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademicStructureService
{
    /**
     * @param  array{name: string, sort_order?: int}  $data
     */
    public function createClass(AcademicYear $academicYear, array $data): SchoolClass
    {
        return DB::transaction(function () use ($academicYear, $data): SchoolClass {
            $academicYear = AcademicYear::query()->lockForUpdate()->findOrFail($academicYear->getKey());
            $this->ensureYearEditable($academicYear);

            return $academicYear->classes()->create($data);
        });
    }

    /**
     * @param  array{name: string, capacity?: int|null}  $data
     */
    public function createSection(SchoolClass $schoolClass, array $data): Section
    {
        return DB::transaction(function () use ($schoolClass, $data): Section {
            $schoolClass = SchoolClass::query()
                ->with('academicYear')
                ->lockForUpdate()
                ->findOrFail($schoolClass->getKey());
            $this->ensureYearEditable($schoolClass->academicYear);

            return $schoolClass->sections()->create([
                ...$data,
                'academic_year_id' => $schoolClass->academic_year_id,
            ]);
        });
    }

    /**
     * @param  list<int>  $subjectIds
     */
    public function syncClassSubjects(SchoolClass $schoolClass, array $subjectIds): void
    {
        DB::transaction(function () use ($schoolClass, $subjectIds): void {
            $schoolClass = SchoolClass::query()
                ->with('academicYear')
                ->lockForUpdate()
                ->findOrFail($schoolClass->getKey());
            $this->ensureYearEditable($schoolClass->academicYear);

            $subjects = Subject::query()->whereKey($subjectIds)->get();

            if ($subjects->count() !== count(array_unique($subjectIds))) {
                throw ValidationException::withMessages([
                    'subject_ids' => 'One or more selected subjects do not exist.',
                ]);
            }

            $existing = ClassSubject::query()
                ->forAcademicYear($schoolClass->academic_year_id)
                ->where('class_id', $schoolClass->id)
                ->pluck('subject_id')
                ->all();
            $toDelete = array_diff($existing, $subjectIds);
            $toCreate = array_diff($subjectIds, $existing);

            if ($toDelete !== []) {
                ClassSubject::query()
                    ->forAcademicYear($schoolClass->academic_year_id)
                    ->where('class_id', $schoolClass->id)
                    ->whereIn('subject_id', $toDelete)
                    ->delete();
            }

            foreach ($toCreate as $subjectId) {
                ClassSubject::query()->create([
                    'academic_year_id' => $schoolClass->academic_year_id,
                    'class_id' => $schoolClass->id,
                    'subject_id' => $subjectId,
                ]);
            }
        });
    }

    public function assignTeacherToSection(Staff $staff, Section $section): TeacherClassAssignment
    {
        return DB::transaction(function () use ($staff, $section): TeacherClassAssignment {
            $section = Section::query()->with('academicYear')->lockForUpdate()->findOrFail($section->getKey());
            $staff = Staff::query()->lockForUpdate()->findOrFail($staff->getKey());
            $this->ensureYearEditable($section->academicYear);
            $this->ensureStaffActive($staff);

            return TeacherClassAssignment::query()->firstOrCreate([
                'academic_year_id' => $section->academic_year_id,
                'staff_id' => $staff->id,
                'section_id' => $section->id,
            ]);
        });
    }

    public function assignTeacherToSubject(Staff $staff, Section $section, Subject $subject): TeacherSubjectAssignment
    {
        return DB::transaction(function () use ($staff, $section, $subject): TeacherSubjectAssignment {
            $section = Section::query()->with('academicYear')->lockForUpdate()->findOrFail($section->getKey());
            $staff = Staff::query()->lockForUpdate()->findOrFail($staff->getKey());
            $this->ensureYearEditable($section->academicYear);
            $this->ensureStaffActive($staff);

            if (! ClassSubject::query()
                ->forAcademicYear($section->academic_year_id)
                ->where('class_id', $section->class_id)
                ->where('subject_id', $subject->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'subject_id' => 'Assign this subject to the class before assigning a teacher.',
                ]);
            }

            $hasClassAssignment = TeacherClassAssignment::query()
                ->forAcademicYear($section->academic_year_id)
                ->where('staff_id', $staff->id)
                ->where('section_id', $section->id)
                ->exists();

            if (! $hasClassAssignment) {
                throw ValidationException::withMessages([
                    'staff_id' => 'Assign the teacher to this section before assigning a subject.',
                ]);
            }

            return TeacherSubjectAssignment::query()->firstOrCreate([
                'academic_year_id' => $section->academic_year_id,
                'staff_id' => $staff->id,
                'section_id' => $section->id,
                'subject_id' => $subject->id,
            ]);
        });
    }

    private function ensureYearEditable(AcademicYear $academicYear): void
    {
        if ($academicYear->status === AcademicYearStatus::Locked) {
            throw ValidationException::withMessages([
                'academic_year' => 'This academic year is locked and cannot be changed.',
            ]);
        }
    }

    private function ensureStaffActive(Staff $staff): void
    {
        if ($staff->status !== 'active') {
            throw ValidationException::withMessages([
                'staff_id' => 'Only active staff can be assigned to a class or subject.',
            ]);
        }

        if (! $staff->is_teacher) {
            throw ValidationException::withMessages([
                'staff_id' => 'Only teaching staff can be assigned to a class or subject.',
            ]);
        }
    }
}
