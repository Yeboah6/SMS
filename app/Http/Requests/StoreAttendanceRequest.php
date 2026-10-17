<?php

namespace App\Http\Requests;

use App\Models\AcademicYear;
use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user || (! $user->can('attendance.mark') && ! $user->can('attendance.update'))) {
            return false;
        }

        $year = AcademicYear::query()->find($this->integer('academic_year_id'));
        $section = Section::query()->find($this->integer('section_id'));

        if (! $year || ! $section || $section->academic_year_id !== $year->id) {
            return false;
        }

        if ($user->hasRole('Teacher') && ! $user->hasRole('Super Admin')) {
            abort_unless($section->teacherClassAssignments()
                ->forAcademicYear($year)
                ->whereHas('staff', fn ($staff) => $staff->where('user_id', $user->id))
                ->exists(), 404);
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'attendance_date' => ['required', 'date_format:Y-m-d'],
            'records' => ['required', 'array', 'min:1', 'max:2000'],
            'records.*.student_id' => ['required', 'integer', 'distinct', 'exists:students,id'],
            'records.*.status' => ['required', Rule::in(['present', 'absent', 'late', 'excused'])],
            'records.*.remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['academic_year_id', 'section_id', 'attendance_date'])) {
                    return;
                }

                $year = AcademicYear::query()->find($this->integer('academic_year_id'));
                if (! $year) {
                    return;
                }

                $date = $this->date('attendance_date');
                if ($year->status->value === 'locked') {
                    $validator->errors()->add(
                        'academic_year_id',
                        'Attendance cannot be changed in a locked academic year.',
                    );
                } elseif ($date < $year->start_date || $date > $year->end_date) {
                    $validator->errors()->add(
                        'attendance_date',
                        'The attendance date must be within the selected academic year.',
                    );
                }
            },
        ];
    }
}
