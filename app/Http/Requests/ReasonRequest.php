<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A written reason for reversing a purchase. Authorised by the controller's policy check. */
class ReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:1000']];
    }
}
