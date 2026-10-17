<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RenewBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('library.renew') ?? false;
    }

    public function rules(): array
    {
        return [
            'due_date' => ['required', 'date', 'after:today'],
        ];
    }
}
