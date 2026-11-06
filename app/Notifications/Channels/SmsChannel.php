<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\NoticePublishedNotification;
use App\Services\TwilioSmsGateway;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class SmsChannel
{
    public function __construct(private TwilioSmsGateway $gateway) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof NoticePublishedNotification || ! $notifiable instanceof User) {
            throw new InvalidArgumentException('SMS notice delivery requires a user and a published notice notification.');
        }

        $recipients = $notifiable->routeNotificationForSms();

        if ($recipients === []) {
            Log::warning('A published notice SMS was skipped because the recipient has no linked phone number.', [
                'notice_id' => $notification->notice->id,
                'notifiable_id' => $notifiable->id,
            ]);

            return;
        }

        foreach ($recipients as $recipient) {
            $this->gateway->send($recipient, $notification->toSms($notifiable));
        }
    }
}
