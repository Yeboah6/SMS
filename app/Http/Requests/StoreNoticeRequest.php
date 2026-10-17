<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('notices.create') ?? false;
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
