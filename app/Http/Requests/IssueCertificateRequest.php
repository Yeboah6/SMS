<?php

namespace App\Http\Requests;

use App\Models\CertificateRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The office issues a certificate directly (no portal request). */
class IssueCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('certificates.issue') ?? false;
    }

    public function rules(): array
    {
        return [
            'admission_number' => ['required', 'string', 'max:50', Rule::exists('students', 'admission_number')->whereNull('deleted_at')],
            'certificate_type' => ['required', Rule::in(CertificateRequest::TYPES)],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'review_comments' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['admission_number.exists' => 'No student has this admission number.'];
    }
}