<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreStudentRequest extends StudentProfileRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('students.create')
            && $this->user()?->can('guardians.create');
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'admission_number' => ['required', 'string', 'max:50', Rule::unique('students', 'admission_number')],
        ];
    }
}
