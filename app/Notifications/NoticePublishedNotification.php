<?php

namespace App\Notifications;

use App\Models\Notice;
use App\Models\SchoolSetting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NoticePublishedNotification extends Notification
{
    public function __construct(
        public Notice $notice,
        public array $channels = ['database'],
        public ?string $publishedByName = null,
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
        ]));
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
        return (new MailMessage)
            ->subject($this->notice->title)
            ->line($this->notice->title)
            ->line($this->notice->body)
            ->action('View notice', route('notices.show', $this->notice->id));
    }
}