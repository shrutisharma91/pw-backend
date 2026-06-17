<?php

namespace App\Notifications;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InfectedDocumentAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Document $document,
        private string $threat,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('FinZ Admin — Infected document quarantined')
            ->line("Document #{$this->document->id} was quarantined after a virus scan.")
            ->line("Threat detected: {$this->threat}")
            ->salutation('FinZ Security Team');
    }
}
