<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IssueBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('library.issue') ?? false;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'due_date' => ['required', 'date', 'after:today'],
        ];
    }
}
