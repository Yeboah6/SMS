<?php

namespace App\Services;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\Enrolment;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class StudentProfileService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Student
    {
        return DB::transaction(function () use ($data): Student {
            $guardians = $data['guardians'];
            $photo = $data['photo'] ?? null;
            unset($data['guardians']);
            unset($data['photo']);

            $student = Student::query()->create($data);
            if ($photo instanceof UploadedFile) {
                $student->update(['photo_path' => $this->storePhoto($photo, $student->id)]);
            }
            $this->syncGuardians($student, $guardians);

            return $student;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Student $student, array $data): Student
    {
        $previousPhotoPath = $student->photo_path;

        $student = DB::transaction(function () use ($student, $data): Student {
            $student = Student::query()->lockForUpdate()->findOrFail($student->getKey());
            $guardians = $data['guardians'];
            $photo = $data['photo'] ?? null;
            unset($data['guardians']);
            unset($data['photo']);
            if ($photo instanceof UploadedFile) {
                $data['photo_path'] = $this->storePhoto($photo, $student->id);
            }

            $student->update($data);
            $this->syncGuardians($student, $guardians, updateExisting: true);

            return $student->refresh();
        });

        if ($previousPhotoPath && $previousPhotoPath !== $student->photo_path) {
            Storage::disk('local')->delete($previousPhotoPath);
        }

        return $student;
    }

    /**
     * @param  array{academic_year_id: int, class_id: int, section_id: int, enrolment_date: string}  $data
     */
    public function enrol(Student $student, array $data): Enrolment
    {
        return DB::transaction(function () use ($student, $data): Enrolment {
            $student = Student::query()->lockForUpdate()->findOrFail($student->getKey());
            $academicYear = AcademicYear::query()->lockForUpdate()->findOrFail($data['academic_year_id']);

            if ($academicYear->status === AcademicYearStatus::Locked) {
                throw ValidationException::withMessages([
                    'academic_year_id' => 'Enrolments cannot be changed in a locked academic year.',
                ]);
            }

            if (Enrolment::query()
                ->forAcademicYear($academicYear)
                ->where('student_id', $student->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'academic_year_id' => 'This student already has an enrolment in that academic year.',
                ]);
            }

            $schoolClass = SchoolClass::query()->lockForUpdate()->findOrFail($data['class_id']);

            if ($schoolClass->academic_year_id !== $academicYear->id) {
                throw ValidationException::withMessages([
                    'class_id' => 'Select a class belonging to the chosen academic year.',
                ]);
            }

            $section = Section::query()
                ->whereKey($data['section_id'])
                ->where('class_id', $schoolClass->id)
                ->where('academic_year_id', $academicYear->id)
                ->first();

            if (! $section) {
                throw ValidationException::withMessages([
                    'section_id' => 'Select a section belonging to the chosen class and academic year.',
                ]);
            }

            $enrolmentDate = Carbon::parse($data['enrolment_date'])->toDateString();

            if ($enrolmentDate < $academicYear->start_date->toDateString() || $enrolmentDate > $academicYear->end_date->toDateString()) {
                throw ValidationException::withMessages([
                    'enrolment_date' => 'The enrolment date must be within the academic year.',
                ]);
            }

            return Enrolment::query()->create([
                ...$data,
                'student_id' => $student->id,
                'status' => 'active',
            ]);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $guardians
     */
    private function syncGuardians(Student $student, array $guardians, bool $updateExisting = false): void
    {
        $relations = [];

        foreach ($guardians as $data) {
            $guardianId = $data['guardian_id'] ?? null;
            if ($guardianId) {
                $guardian = Guardian::query()->findOrFail($guardianId);

                if ($updateExisting) {
                    $guardian->update([
                        'name' => $data['name'],
                        'phone' => $data['phone'],
                        'email' => $data['email'] ?? null,
                        'occupation' => $data['occupation'] ?? null,
                        'address' => $data['address'] ?? null,
                    ]);
                }
            } else {
                $guardian = Guardian::query()->create([
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'email' => $data['email'] ?? null,
                    'occupation' => $data['occupation'] ?? null,
                    'address' => $data['address'] ?? null,
                ]);
            }

            $relations[$guardian->id] = [
                'relationship' => $data['relationship'],
                'is_primary' => (bool) $data['is_primary'],
                'lives_with_student' => (bool) ($data['lives_with_student'] ?? false),
            ];
        }

        $student->guardians()->sync($relations);
    }

    private function storePhoto(UploadedFile $photo, int $studentId): string
    {
        $path = $photo->storeAs(
            "student-photos/{$studentId}",
            Str::uuid().'.'.$photo->extension(),
            'local',
        );

        if (! $path) {
            throw new \RuntimeException('The student photo could not be stored.');
        }

        return $path;
    }
}
