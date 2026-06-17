<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Outbound notification dispatch for SMS, email, WhatsApp, and push (Phase 12).
 */
class NotificationService
{
    /**
     * Send a one-off test notification from the template manager.
     */
    public function sendTest(string $channel, string $to, string $body, ?string $subject = null): bool
    {
        return match ($channel) {
            'sms'      => $this->sendSms($to, $body),
            'email'    => $this->sendEmail($to, $subject ?? 'Test Notification', $body),
            'whatsapp' => $this->sendWhatsapp($to, $body),
            'push'     => $this->sendPush($to, $body),
            default    => false,
        };
    }

    public function sendSms(string $to, string $body): bool
    {
        if ($this->shouldSimulate()) {
            Log::info("NotificationService [SMS] → {$to}: {$body}");
            return true;
        }

        $apiKey   = config('services.msg91.api_key');
        $senderId = config('services.msg91.sender_id', 'FINZLM');

        if (empty($apiKey)) {
            Log::warning('NotificationService: MSG91 API key not configured.');
            return false;
        }

        $response = Http::withHeaders(['authkey' => $apiKey])
            ->timeout(30)
            ->post('https://api.msg91.com/api/v5/flow/', [
                'template_id' => config('services.msg91.template_id'),
                'short_url'   => '0',
                'recipients'  => [
                    ['mobiles' => $this->normalizeMobile($to), 'message' => $body],
                ],
                'sender'      => $senderId,
            ]);

        return $response->successful();
    }

    public function sendEmail(string $to, string $subject, string $body): bool
    {
        if ($this->shouldSimulate()) {
            Log::info("NotificationService [Email] → {$to} | {$subject}: {$body}");
            return true;
        }

        try {
            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('NotificationService: email send failed — ' . $e->getMessage());
            return false;
        }
    }

    public function sendWhatsapp(string $to, string $body): bool
    {
        if ($this->shouldSimulate()) {
            Log::info("NotificationService [WhatsApp] → {$to}: {$body}");
            return true;
        }

        $token      = config('services.whatsapp.token');
        $phoneId    = config('services.whatsapp.phone_number_id');

        if (empty($token) || empty($phoneId)) {
            Log::warning('NotificationService: WhatsApp API not configured.');
            return false;
        }

        $response = Http::withToken($token)
            ->timeout(30)
            ->post("https://graph.facebook.com/v19.0/{$phoneId}/messages", [
                'messaging_product' => 'whatsapp',
                'to'                => $this->normalizeMobile($to),
                'type'              => 'text',
                'text'              => ['body' => $body],
            ]);

        return $response->successful();
    }

    public function sendPush(string $to, string $body): bool
    {
        if ($this->shouldSimulate()) {
            Log::info("NotificationService [Push] → {$to}: {$body}");
            return true;
        }

        Log::warning('NotificationService: push provider not configured.');
        return false;
    }

    private function shouldSimulate(): bool
    {
        return app()->environment('local', 'testing');
    }

    private function normalizeMobile(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? $number;

        if (strlen($digits) === 10) {
            return '91' . $digits;
        }

        return $digits;
    }
}
