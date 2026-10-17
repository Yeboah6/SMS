<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'notification_settings' => ['required', 'array:database_notices,email_notices'],
            'notification_settings.database_notices' => ['required', 'boolean'],
            'notification_settings.email_notices' => ['required', 'boolean'],
        ];
    }
}