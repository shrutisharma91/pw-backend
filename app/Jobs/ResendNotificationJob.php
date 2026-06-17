<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use App\Services\NotificationService;

class ResendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    // Exponential backoff: retry after 30s, 60s, 120s
    public array $backoff = [30, 60, 120];

    public function __construct(private int $logId) {}

    public function handle(NotificationService $notificationService): void
    {
        $log = DB::table('communication_logs')->where('id', $this->logId)->first();

        if (!$log) {
            \Log::warning("ResendNotificationJob: log #{$this->logId} not found.");
            return;
        }

        if ($log->status !== 'failed') {
            // Already delivered in a previous attempt — skip silently
            return;
        }

        // Mark as retrying
        DB::table('communication_logs')->where('id', $this->logId)->update([
            'status'     => 'sent',
            'sent_at'    => now(),
            'updated_at' => now(),
        ]);

        // Fetch original body from template
        $body    = $this->resolveBody($log);
        $success = false;

        try {
            $success = match ($log->channel) {
                'sms'      => $notificationService->sendSms($log->recipient, $body),
                'email'    => $notificationService->sendEmail($log->recipient, 'Resent Notification', $body),
                'whatsapp' => $notificationService->sendWhatsapp($log->recipient, $body),
                default    => false,
            };
        } catch (\Exception $e) {
            \Log::error("ResendNotificationJob: send failed for log #{$this->logId}: " . $e->getMessage());
        }

        DB::table('communication_logs')->where('id', $this->logId)->update([
            'status'       => $success ? 'sent' : 'failed',
            'failed_at'    => $success ? null : now(),
            'failure_reason' => $success ? null : 'Resend attempt failed.',
            'updated_at'   => now(),
        ]);
    }

    private function resolveBody(object $log): string
    {
        if (empty($log->template_key)) {
            return '';
        }

        $version = DB::table('notification_template_versions')
            ->join('notification_templates', 'notification_templates.id', '=', 'notification_template_versions.template_id')
            ->where('notification_templates.template_key', $log->template_key)
            ->where('notification_template_versions.is_active', true)
            ->value('notification_template_versions.body');

        return $version ?? '';
    }

    public function failed(\Throwable $e): void
    {
        DB::table('communication_logs')->where('id', $this->logId)->update([
            'status'         => 'failed',
            'failure_reason' => 'All resend attempts exhausted: ' . $e->getMessage(),
            'failed_at'      => now(),
            'updated_at'     => now(),
        ]);

        \Log::error("ResendNotificationJob permanently failed for log #{$this->logId}: " . $e->getMessage());
    }
}