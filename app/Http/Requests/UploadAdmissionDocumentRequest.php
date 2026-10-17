<?php

namespace App\Http\Requests;

use App\Models\Admission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadAdmissionDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admission = $this->route('admission');

        return $admission instanceof Admission
            && ($this->user()?->can('admissions.documents.manage') ?? false);
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(['application', 'birth_certificate', 'identity', 'medical', 'transfer_certificate', 'other'])],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }
}
