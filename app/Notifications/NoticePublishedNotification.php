<?php

namespace App\Notifications;

use App\Models\Notice;
use App\Models\SchoolSetting;
use App\Notifications\Channels\SmsChannel;
use App\Services\SchoolSettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NoticePublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Notice $notice,
        public array $channels = ['database'],
        public ?string $publishedByName = null,
        public array $templates = [],
    ) {}

    /** Resolve the enabled channels from the school's notification settings. */
    public static function channelsFromSettings(): array
    {
        $settings = SchoolSetting::query()
            ->where('singleton_key', 'primary')
            ->first()?->notification_settings ?? [];

        return array_values(array_filter([
            ($settings['database_notices'] ?? true) ? 'database' : null,
            ($settings['email_notices'] ?? true) ? 'mail' : null,
            ($settings['sms_notices'] ?? false) ? SmsChannel::class : null,
        ]));
    }

    /**
     * @return array{notice_email_subject: string, notice_email_body: string, notice_sms_body: string}
     */
    public static function templatesFromSettings(): array
    {
        $defaults = app(SchoolSettingsService::class)->defaults()['notification_settings'];
        $settings = SchoolSetting::query()
            ->where('singleton_key', 'primary')
            ->first()?->notification_settings ?? [];

        return [
            'notice_email_subject' => $settings['notice_email_subject'] ?? $defaults['notice_email_subject'],
            'notice_email_body' => $settings['notice_email_body'] ?? $defaults['notice_email_body'],
            'notice_sms_body' => $settings['notice_sms_body'] ?? $defaults['notice_sms_body'],
        ];
    }

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toArray(object $notifiable): array
    {
        $isAuthor = $notifiable->id === $this->notice->created_by;

        return [
            'notice_id' => $this->notice->id,
            'title' => $isAuthor ? 'Your notice was published' : $this->notice->title,
            'body' => $isAuthor && $this->publishedByName
                ? "{$this->publishedByName} published \"{$this->notice->title}\" to {$this->notice->audience}."
                : $this->notice->title,
            'audience' => $this->notice->audience,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $values = $this->templateValues();
        $templates = array_replace(self::templatesFromSettings(), $this->templates);

        return (new MailMessage)
            ->subject(strtr($templates['notice_email_subject'], $values))
            ->line(strtr($templates['notice_email_body'], $values));
    }

    public function toSms(object $notifiable): string
    {
        $templates = array_replace(self::templatesFromSettings(), $this->templates);

        return strtr($templates['notice_sms_body'], $this->templateValues());
    }

    /**
     * @return array<string, string>
     */
    private function templateValues(): array
    {
        return [
            '{title}' => $this->notice->title,
            '{body}' => $this->notice->body,
            '{url}' => route('notices.show', $this->notice),
            '{audience}' => $this->notice->audience,
        ];
    }
}
