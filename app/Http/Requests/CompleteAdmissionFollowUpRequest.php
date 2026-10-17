<?php

namespace App\Http\Requests;

use App\Models\AdmissionFollowUp;
use Illuminate\Foundation\Http\FormRequest;

class CompleteAdmissionFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        $followUp = $this->route('followUp');

        return $followUp instanceof AdmissionFollowUp
            && ($this->user()?->can('admissions.followups.manage') ?? false);
    }

    public function rules(): array
    {
        return [
            'outcome' => ['required', 'string', 'max:5000'],
        ];
    }
}
