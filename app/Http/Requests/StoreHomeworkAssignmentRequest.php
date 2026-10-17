<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHomeworkAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('homework.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['required', 'string', 'min:10', 'max:10000'],
            'assigned_date' => ['required', 'date', 'after_or_equal:today'],
            'due_date' => ['required', 'date', 'after_or_equal:assigned_date'],
            'max_points' => ['required', 'numeric', 'min:1', 'max:10000'],
        ];
    }
}
