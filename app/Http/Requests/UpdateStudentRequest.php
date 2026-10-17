<?php

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends StudentProfileRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student instanceof Student
            && ($this->user()?->can('update', $student) ?? false)
            && ($this->user()?->can('guardians.update') ?? false);
    }

    public function rules(): array
    {
        $student = $this->route('student');

        abort_unless($student instanceof Student, 404);

        return [
            ...parent::rules(),
            'admission_number' => [
                'required', 'string', 'max:50', 'regex:'.StoreStudentRequest::ADMISSION_NUMBER_FORMAT,
                Rule::unique('students', 'admission_number')->ignore($student->id),
            ],
        ];
    }
}