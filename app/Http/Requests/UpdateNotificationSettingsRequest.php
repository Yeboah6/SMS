<?php

namespace App\Http\Requests;

use App\Services\TwilioSmsGateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'notification_settings' => ['required', 'array:database_notices,email_notices,sms_notices,notice_email_subject,notice_email_body,notice_sms_body'],
            'notification_settings.database_notices' => ['required', 'boolean'],
            'notification_settings.email_notices' => ['required', 'boolean'],
            'notification_settings.sms_notices' => ['required', 'boolean'],
            'notification_settings.notice_email_subject' => ['required', 'string', 'max:255'],
            'notification_settings.notice_email_body' => ['required', 'string', 'max:5000'],
            'notification_settings.notice_sms_body' => ['required', 'string', 'max:1600'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    $this->boolean('notification_settings.sms_notices')
                    && ! app(TwilioSmsGateway::class)->isConfigured()
                ) {
                    $validator->errors()->add(
                        'notification_settings.sms_notices',
                        'Configure valid Twilio credentials and exactly one Twilio sender in the server environment before enabling SMS notices.',
                    );
                }

                foreach ([
                    'notice_email_subject',
                    'notice_email_body',
                    'notice_sms_body',
                ] as $key) {
                    $field = "notification_settings.{$key}";
                    $template = $this->input($field);

                    if (! is_string($template)) {
                        continue;
                    }

                    preg_match_all('/\{([^{}]+)\}/', $template, $matches);
                    $unsupported = array_diff(array_unique($matches[1]), ['title', 'body', 'url', 'audience']);

                    if ($unsupported !== []) {
                        $validator->errors()->add(
                            $field,
                            'Use only the supported placeholders: {title}, {body}, {url}, and {audience}.',
                        );
                    }
                }
            },
        ];
    }
}
