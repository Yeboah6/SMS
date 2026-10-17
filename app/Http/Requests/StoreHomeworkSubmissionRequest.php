<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHomeworkSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('portal.homework.submit') ?? false;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:1', 'max:10000'],
        ];
    }
}
