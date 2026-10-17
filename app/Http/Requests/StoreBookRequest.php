<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('library.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'author' => ['required', 'string', 'max:150'],
            'isbn' => ['nullable', 'string', 'max:30', Rule::unique('books', 'isbn')],
            'category' => ['required', 'string', 'max:100'],
            'publisher' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:4000'],
            'copies' => ['required', 'integer', 'min:1', 'max:100'],
            'shelf_location' => ['nullable', 'string', 'max:100'],
        ];
    }
}
