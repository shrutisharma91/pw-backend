<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

/*
|--------------------------------------------------------------------------
| SendOTPNotification
|--------------------------------------------------------------------------
| Sends the 6-digit OTP to the user for MFA verification (Screen 02).
|
| Channels supported:
|   - Mail → email channel
|
| Usage in MFAService.php:
|   Notification::route('mail', 'finzwork10@gmail.com')->notify(new SendOTPNotification($otp));
*/

class SendOTPNotification extends Notification
{
    use Queueable;

    public function __construct(private string $otp) {}

    // Which channels to send through
    public function via(object $notifiable): array
    {
        // Send via mail
        return ['mail'];
    }

    // Email message
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('FinZ Admin — Your Login OTP')
            ->greeting('Hello,')
            ->line('Your one-time password (OTP) for FinZ Admin login is:')
            ->line('**' . $this->otp . '**')
            ->line('This OTP is valid for **5 minutes** only.')
            ->line('If you did not request this, please secure your account immediately.')
            ->salutation('FinZ Security Team');
    }

    // Array representation (for database/in-app notification channel)
    public function toArray(object $notifiable): array
    {
        return [
            'otp'        => $this->otp,
            'expires_in' => 300, // 5 minutes in seconds
        ];
    }
}
