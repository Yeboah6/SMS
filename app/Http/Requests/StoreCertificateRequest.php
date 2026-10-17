<?php

namespace App\Http\Requests;

use App\Models\CertificateRequest as StudentCertificateRequest;
use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user || ! $user->can('portal.certificates.request')) {
            return false;
        }

        if ($user->hasRole('Student')) {
            return Student::query()->where('user_id', $user->id)->exists();
        }

        return $user->hasRole('Parent') && Student::query()->forGuardianAccount($user)->exists();
    }

    public function rules(): array
    {
        return [
            'certificate_type' => ['required', Rule::in(StudentCertificateRequest::TYPES)],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'student_id' => $this->isParentApplicant()
                ? ['required', 'integer', 'exists:students,id']
                : ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->isParentApplicant() || $validator->errors()->has('student_id')) {
                    return;
                }

                $isLinkedStudent = Student::query()
                    ->whereKey($this->integer('student_id'))
                    ->forGuardianAccount($this->user())
                    ->exists();

                if (! $isLinkedStudent) {
                    $validator->errors()->add('student_id', 'Select a student linked to your account.');
                }
            },
        ];
    }

    private function isParentApplicant(): bool
    {
        $user = $this->user();

        return $user !== null && ! $user->hasRole('Student') && $user->hasRole('Parent');
    }
}
