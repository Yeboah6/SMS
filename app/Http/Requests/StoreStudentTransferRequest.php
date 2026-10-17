<?php

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;

class StoreStudentTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');
        $user = $this->user();

        return $student instanceof Student
            && $user !== null
            && $user->can('students.transfer')
            && $user->can('view', $student);
    }

    public function rules(): array
    {
        return [
            'enrolment_id' => ['required', 'integer', 'exists:enrolments,id'],
            'transfer_date' => ['required', 'date', 'before_or_equal:today'],
            'destination_school' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
