<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NoticePublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $noticeId,
        public string $title,
        public string $body,
        public array $channels = ['database', 'mail'],
    ) {}

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('School notice: '.$this->title)
            ->greeting('A new school notice is available')
            ->line($this->body)
            ->action('View notices', route('notices.index'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'notice_id' => $this->noticeId,
            'title' => $this->title,
            'body' => $this->body,
            'url' => route('notices.index'),
        ];
    }
}
