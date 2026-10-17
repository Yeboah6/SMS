<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreStudentRequest extends StudentProfileRequest
{
    /** Letters, digits, spaces and / - _ . so legacy numbers such as 2019/045 or ADM-12 are accepted. */
    public const ADMISSION_NUMBER_FORMAT = '/^[\pL\pN][\pL\pN\s\/\-_.]*$/u';

    public function authorize(): bool
    {
        return $this->user()?->can('students.create')
            && $this->user()?->can('guardians.create');
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            // Optional: leave blank to generate the next number (ADM0001, ...).
            'admission_number' => [
                'nullable', 'string', 'max:50', 'regex:'.self::ADMISSION_NUMBER_FORMAT,
                Rule::unique('students', 'admission_number'),
            ],
        ];
    }
}