<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminPasswordResetNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $url,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = trim((string) ($notifiable->name ?? ''));
        $minutes = (int) config('auth.passwords.users.expire', 30);

        return (new MailMessage)
            ->subject('Reset your Lars Möller administration password')
            ->greeting($name !== '' ? "Hello {$name}," : 'Hello,')
            ->line('A password reset was requested for your Lars Möller administration account.')
            ->action('Reset password', $this->url)
            ->line("This link expires in {$minutes} minutes.")
            ->line('If you did not request this reset, no action is required.');
    }
}
