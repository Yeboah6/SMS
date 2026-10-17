<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Who may edit which draft is decided in NoticeService::updateDraft(). */
class UpdateNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->can('notices.create') || $user->can('notices.publish'));
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:10000'],
            'audience' => ['required', Rule::in(['all', 'staff', 'teachers', 'students', 'parents'])],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }
}