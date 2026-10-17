<?php

namespace App\Http\Requests;

use App\Models\LeaveRequest;
use App\Models\Staff;
use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        if ($this->isStaffApplicant()) {
            return true;
        }

        if (! $user->can('portal.leave.apply')) {
            return false;
        }

        if ($user->hasRole('Student')) {
            return Student::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->exists();
        }

        return $user->hasRole('Parent');
    }

    public function rules(): array
    {
        return [
            'leave_type' => ['required', 'in:'.implode(',', LeaveRequest::TYPES)],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'student_id' => $this->isParentApplicant()
                ? ['required', 'integer', 'exists:students,id']
                : ['prohibited'],
        ];
    }

    private function isStaffApplicant(): bool
    {
        $user = $this->user();
        if (! $user || ! $user->can('leave.apply')) {
            return false;
        }

        return Staff::query()->where('user_id', $user->id)->where('status', 'active')->exists();
    }

    private function isParentApplicant(): bool
    {
        $user = $this->user();
        if ($user === null || $this->isStaffApplicant() || $user->hasRole('Student')) {
            return false;
        }

        return $user->hasRole('Parent');
    }
}
