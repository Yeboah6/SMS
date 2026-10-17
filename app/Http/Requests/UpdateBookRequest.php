<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Mirror any extra rules from StoreBookRequest. 'library.update' is a guess at your permission name. */
class UpdateBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('library.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'author' => ['required', 'string', 'max:150'],
            'isbn' => ['nullable', 'string', 'max:30', Rule::unique('books', 'isbn')->ignore($this->route('book'))],
            'category' => ['required', 'string', 'max:100'],
            'publisher' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:4000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}