<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class StudentProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'in:female,male,other,prefer_not_to_say'],
            'blood_group' => ['nullable', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'religion' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'admission_date' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['required', 'in:active,inactive,graduated,transferred,left'],
            'guardians' => ['required', 'array', 'min:1'],
            'guardians.*.guardian_id' => ['nullable', 'integer', 'distinct', Rule::exists('guardians', 'id')->whereNull('deleted_at')],
            'guardians.*.name' => ['nullable', 'string', 'max:255'],
            'guardians.*.phone' => ['nullable', 'string', 'max:50'],
            'guardians.*.email' => ['nullable', 'email', 'max:255'],
            'guardians.*.occupation' => ['nullable', 'string', 'max:150'],
            'guardians.*.address' => ['nullable', 'string', 'max:2000'],
            'guardians.*.relationship' => ['required', 'string', 'max:50'],
            'guardians.*.is_primary' => ['required', 'boolean'],
            'guardians.*.lives_with_student' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $guardians = $this->input('guardians', []);
            if (! is_array($guardians)) {
                return;
            }

            $primaryCount = 0;

            foreach ($guardians as $index => $guardian) {
                if (! is_array($guardian)) {
                    continue;
                }

                if (! ($guardian['guardian_id'] ?? null)) {
                    if (blank($guardian['name'] ?? null)) {
                        $validator->errors()->add("guardians.{$index}.name", 'Enter a guardian name.');
                    }

                    if (blank($guardian['phone'] ?? null)) {
                        $validator->errors()->add("guardians.{$index}.phone", 'Enter a guardian phone number.');
                    }
                }

                if (in_array($guardian['is_primary'] ?? false, [true, 1, '1'], true)) {
                    $primaryCount++;
                }
            }

            if ($primaryCount !== 1) {
                $validator->errors()->add('guardians', 'Select exactly one primary guardian.');
            }
        });
    }
}
