<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('exams.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'name' => ['required', 'string', 'max:150'],
            'exam_type' => ['required', Rule::in(['unit_test', 'mid_term', 'final', 'practical', 'other'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'subjects' => ['required', 'array', 'min:1', 'max:50'],
            'subjects.*.subject_id' => ['required', 'integer', 'distinct', 'exists:subjects,id'],
            'subjects.*.exam_date' => ['required', 'date', 'after_or_equal:start_date', 'before_or_equal:end_date'],
            'subjects.*.max_marks' => ['required', 'numeric', 'min:1', 'max:100000'],
            'subjects.*.pass_marks' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['start_date', 'end_date', 'subjects'])) {
                    return;
                }

                foreach ($this->input('subjects', []) as $index => $subject) {
                    if (
                        is_array($subject)
                        && isset($subject['max_marks'], $subject['pass_marks'])
                        && (float) $subject['pass_marks'] > (float) $subject['max_marks']
                    ) {
                        $validator->errors()->add(
                            "subjects.{$index}.pass_marks",
                            'Pass marks cannot exceed maximum marks.',
                        );
                    }
                }
            },
        ];
    }
}
