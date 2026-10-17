<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RevokeCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('certificates.revoke') ?? false;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:10', 'max:2000']];
    }
}