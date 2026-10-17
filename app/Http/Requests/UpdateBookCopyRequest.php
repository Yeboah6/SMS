<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookCopyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('library.update') ?? false;
    }

    public function rules(): array
    {
        return ['shelf_location' => ['nullable', 'string', 'max:100']];
    }
}