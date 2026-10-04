<?php

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;

class StoreStudentDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student instanceof Student
            && ($this->user()?->can('manageDocuments', $student) ?? false);
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'in:birth_certificate,transfer_certificate,medical,other'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }
}
