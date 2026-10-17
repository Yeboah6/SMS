<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReturnBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('library.return') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
