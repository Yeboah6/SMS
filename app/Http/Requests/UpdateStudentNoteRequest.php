<?php

namespace App\Http\Requests;

use App\Models\StudentNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateStudentNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $note = $this->route('note');

        return $note instanceof StudentNote
            && $this->user()?->can('update', $note) === true;
    }

    public function rules(): array
    {
        return [
            'note_type' => ['required', 'in:academic,behaviour,welfare,parent_communication,medical,general'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:10000'],
            'note_date' => ['required', 'date', 'before_or_equal:today'],
            'visibility' => ['required', 'in:staff_only,guardians,student'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'remove_attachment' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->hasFile('attachment') && $this->boolean('remove_attachment')) {
                    $validator->errors()->add(
                        'remove_attachment',
                        'Choose a replacement attachment or remove the current one, not both.',
                    );
                }
            },
        ];
    }
}
