<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddBookCopiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('library.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'count' => ['required', 'integer', 'min:1', 'max:100'],
            'shelf_location' => ['nullable', 'string', 'max:100'],
        ];
    }
}
