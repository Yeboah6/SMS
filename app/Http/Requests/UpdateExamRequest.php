<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('exams.update');
    }

    /**
     * Mirror any extra rules from StoreExamRequest. Class and academic year are not editable.
     * Term and class-subject membership are verified in ExamService::update().
     */
    public function rules(): array
    {
        return [
            'term_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:150'],
            'exam_type' => ['required', Rule::in(['unit_test', 'mid_term', 'final', 'practical', 'other'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*.subject_id' => ['required', 'integer', 'distinct'],
            'subjects.*.exam_date' => ['required', 'date', 'after_or_equal:start_date', 'before_or_equal:end_date'],
            'subjects.*.max_marks' => ['required', 'numeric', 'min:1'],
            'subjects.*.pass_marks' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach ((array) $this->input('subjects', []) as $index => $row) {
                if (isset($row['max_marks'], $row['pass_marks']) && (float) $row['pass_marks'] > (float) $row['max_marks']) {
                    $validator->errors()->add("subjects.$index.pass_marks", 'The pass mark cannot exceed the maximum.');
                }
            }
        });
    }
}