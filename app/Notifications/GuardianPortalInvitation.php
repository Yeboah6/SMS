<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GuardianPortalInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('You\'re invited to the '.config('app.name').' parent portal')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A parent portal account has been created for you. You can use it to follow your child\'s progress and school updates.')
            ->action('Set your password', $url)
            ->line("This link expires in {$minutes} minutes. If it expires, ask the school office to resend your invitation.")
            ->line('If you were not expecting this, you can ignore this email.');
    }
}