<?php

namespace App\Http\Requests;

use App\Models\Admission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdmissionFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admission = $this->route('admission');

        return $admission instanceof Admission
            && ($this->user()?->can('admissions.followups.manage') ?? false);
    }

    public function rules(): array
    {
        return [
            'follow_up_at' => ['required', 'date', 'after_or_equal:now'],
            'method' => ['required', Rule::in(['phone', 'email', 'in_person', 'other'])],
            'notes' => ['required', 'string', 'max:5000'],
        ];
    }
}
