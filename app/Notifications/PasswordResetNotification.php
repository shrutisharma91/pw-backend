<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

/*
|--------------------------------------------------------------------------
| PasswordResetNotification
|--------------------------------------------------------------------------
| Sends the password reset link to the admin's email (Screen 03).
|
| Called from PasswordResetController::sendResetLink()
|
| Usage:
|   $user->notify(new PasswordResetNotification($resetUrl));
|
| The reset link expires in 15 minutes as per the spec.
*/

class PasswordResetNotification extends Notification
{
    use Queueable;

    public function __construct(private string $resetUrl) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('FinZ Admin — Password Reset Request')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('We received a request to reset your FinZ Admin password.')
            ->action('Reset Password', $this->resetUrl)
            ->line('This link will expire in **15 minutes**.')
            ->line('If you did not request a password reset, no action is needed.')
            ->line('For security, all your active sessions will be logged out after reset.')
            ->salutation('FinZ Security Team');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'reset_url'  => $this->resetUrl,
            'expires_in' => 15, // minutes
        ];
    }
}
