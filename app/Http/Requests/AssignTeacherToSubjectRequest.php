<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignTeacherToSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('teacher_assignments.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
        ];
    }
}
