<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAcademicYear;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['academic_year_id', 'class_id', 'name', 'capacity'])]
class Section extends Model
{
    use BelongsToAcademicYear;

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function teacherClassAssignments(): HasMany
    {
        return $this->hasMany(TeacherClassAssignment::class);
    }

    public function teacherSubjectAssignments(): HasMany
    {
        return $this->hasMany(TeacherSubjectAssignment::class);
    }

    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function homeworkAssignments(): HasMany
    {
        return $this->hasMany(HomeworkAssignment::class);
    }

    public function students(): HasManyThrough
    {
        return $this->hasManyThrough(Student::class, Enrolment::class, 'section_id', 'id', 'id', 'student_id')
            ->where('enrolments.status', 'active');
    }
}
