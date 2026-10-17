<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimetableSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('timetable.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'teacher_subject_assignment_id' => ['required', 'integer', 'exists:teacher_subject_assignments,id'],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'room' => ['nullable', 'string', 'max:100'],
        ];
    }
}
