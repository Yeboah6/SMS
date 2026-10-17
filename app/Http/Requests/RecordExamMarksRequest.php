<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordExamMarksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('marks.enter')
            || $this->user()?->can('marks.update')
            || false;
    }

    public function rules(): array
    {
        return [
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'records' => ['required', 'array', 'min:1', 'max:200'],
            'records.*.student_id' => ['required', 'integer', 'distinct', 'exists:students,id'],
            'records.*.status' => ['required', Rule::in(['present', 'absent', 'exempt'])],
            'records.*.marks_obtained' => ['nullable', 'numeric', 'min:0', 'required_if:records.*.status,present'],
            'records.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
