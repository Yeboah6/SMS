<?php

namespace App\Http\Requests;

use App\Models\Student;
use App\Models\StudentNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');
        $user = $this->user();

        if (! $student instanceof Student || ! $user) {
            return false;
        }

        if (
            $user->can('students.view')
            && ! Student::query()->visibleTo($user)->whereKey($student->getKey())->exists()
        ) {
            abort(404);
        }

        return $user->can('create', [StudentNote::class, $student]);
    }

    public function rules(): array
    {
        return [
            'note_type' => ['required', Rule::in([
                'academic',
                'behaviour',
                'welfare',
                'parent_communication',
                'medical',
                'general',
            ])],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:10000'],
            'note_date' => ['required', 'date', 'before_or_equal:today'],
            'visibility' => ['required', Rule::in(['staff_only', 'guardians', 'student'])],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}
