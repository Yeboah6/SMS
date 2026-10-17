<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GradeHomeworkSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('homework.grade') ?? false;
    }

    public function rules(): array
    {
        return [
            'score' => ['required', 'numeric', 'min:0', 'max:10000'],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
