<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExportReadyNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $title,
        private string $downloadUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("FinZ Admin — {$this->title} is ready")
            ->greeting('Hello,')
            ->line("Your export \"{$this->title}\" is ready to download.")
            ->action('Download Export', $this->downloadUrl)
            ->line('This link expires after a short period for security.')
            ->salutation('FinZ Admin');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'        => $this->title,
            'download_url' => $this->downloadUrl,
        ];
    }
}
