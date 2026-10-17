<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('certificates.issue') ?? false;
    }

    public function rules(): array
    {
        return [
            'outcome' => ['required', Rule::in(['issued', 'rejected'])],
            'review_comments' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
