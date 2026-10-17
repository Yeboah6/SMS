<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('leave.approve') ?? false;
    }

    public function rules(): array
    {
        return [
            'outcome' => ['required', Rule::in(['approved', 'rejected'])],
            'review_comments' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
