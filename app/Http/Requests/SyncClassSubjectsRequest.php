<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncClassSubjectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('class_subjects.assign') ?? false;
    }

    public function rules(): array
    {
        return [
            'subject_ids' => ['present', 'array'],
            'subject_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('subjects', 'id'),
            ],
        ];
    }
}
