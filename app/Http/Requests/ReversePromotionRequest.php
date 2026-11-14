<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReversePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('students.promote.override') ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
