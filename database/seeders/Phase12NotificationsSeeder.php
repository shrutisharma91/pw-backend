<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\NotificationTemplate;
use App\Models\NotificationTemplateVersion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Frozen Phase 12 templates + communication logs.
 */
class Phase12NotificationsSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'finzwork10@gmail.com')->first();
        $adminId = $admin?->id ?? 1;
        $merchant = Merchant::where('business_name', 'Tech Superstore')->first()
            ?? Merchant::first();

        $templates = [
            [
                'name' => 'OTP SMS',
                'template_key' => 'otp_sms',
                'channel' => 'sms',
                'subject' => null,
                'status' => 'active',
                'body' => 'Your FinZ OTP is {{otp}}. Valid for {{expiry}} minutes.',
            ],
            [
                'name' => 'Loan Approved Email',
                'template_key' => 'loan_approved_email',
                'channel' => 'email',
                'subject' => 'Your FinZ loan is approved',
                'status' => 'active',
                'body' => 'Hi {{name}}, your loan of {{amount}} has been approved.',
            ],
            [
                'name' => 'EMI Reminder Push',
                'template_key' => 'emi_reminder_push',
                'channel' => 'push',
                'subject' => 'EMI due tomorrow',
                'status' => 'active',
                'body' => 'EMI of {{amount}} is due tomorrow for loan {{loan_id}}.',
            ],
            [
                'name' => 'Settlement Alert WhatsApp',
                'template_key' => 'settlement_alert_wa',
                'channel' => 'whatsapp',
                'subject' => null,
                'status' => 'draft',
                'body' => 'Settlement batch {{batch_id}} is ready for {{merchant}}.',
            ],
        ];

        foreach ($templates as $row) {
            $template = NotificationTemplate::updateOrCreate(
                ['template_key' => $row['template_key']],
                [
                    'name' => $row['name'],
                    'channel' => $row['channel'],
                    'subject' => $row['subject'],
                    'variables' => ['otp', 'expiry', 'name', 'amount', 'loan_id', 'batch_id', 'merchant'],
                    'language' => 'en',
                    'status' => $row['status'],
                    'current_version' => 1,
                    'created_by' => $adminId,
                    'approved_by' => $row['status'] === 'active' ? $adminId : null,
                    'approved_at' => $row['status'] === 'active' ? now()->subDays(2) : null,
                ]
            );

            NotificationTemplateVersion::updateOrCreate(
                ['template_id' => $template->id, 'version_number' => 1],
                [
                    'body' => $row['body'],
                    'subject' => $row['subject'],
                    'is_active' => true,
                    'created_by' => $adminId,
                ]
            );
        }

        if (! DB::table('communication_logs')->exists()) {
            $logs = [
                ['channel' => 'email', 'recipient' => 'ops@techsuperstore.com', 'template_key' => 'loan_approved_email', 'provider' => 'ses', 'status' => 'delivered', 'days' => 1, 'cost' => 0.40],
                ['channel' => 'email', 'recipient' => 'rahul@example.com', 'template_key' => 'loan_approved_email', 'provider' => 'ses', 'status' => 'delivered', 'days' => 2, 'cost' => 0.40],
                ['channel' => 'email', 'recipient' => 'kyc@techsuperstore.com', 'template_key' => 'loan_approved_email', 'provider' => 'ses', 'status' => 'failed', 'days' => 3, 'cost' => 0.40],
                ['channel' => 'sms', 'recipient' => '9876543210', 'template_key' => 'otp_sms', 'provider' => 'msg91', 'status' => 'delivered', 'days' => 0, 'cost' => 0.18],
                ['channel' => 'sms', 'recipient' => '9876501234', 'template_key' => 'otp_sms', 'provider' => 'msg91', 'status' => 'delivered', 'days' => 1, 'cost' => 0.18],
                ['channel' => 'sms', 'recipient' => '9123456780', 'template_key' => 'otp_sms', 'provider' => 'msg91', 'status' => 'failed', 'days' => 4, 'cost' => 0.18],
                ['channel' => 'push', 'recipient' => 'device-rahul', 'template_key' => 'emi_reminder_push', 'provider' => 'firebase', 'status' => 'delivered', 'days' => 2, 'cost' => 0.05],
                ['channel' => 'whatsapp', 'recipient' => '9876543210', 'template_key' => 'settlement_alert_wa', 'provider' => 'meta_wa', 'status' => 'sent', 'days' => 5, 'cost' => 0.60],
            ];

            foreach ($logs as $i => $log) {
                $sentAt = now()->subDays($log['days'])->setTime(10, 15 + $i, 0);
                $delivered = in_array($log['status'], ['delivered', 'read', 'clicked'], true);
                DB::table('communication_logs')->insert([
                    'channel' => $log['channel'],
                    'recipient' => $log['recipient'],
                    'template_key' => $log['template_key'],
                    'provider' => $log['provider'],
                    'provider_message_id' => 'MSG-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                    'status' => $log['status'],
                    'failure_reason' => $log['status'] === 'failed' ? 'Provider timeout' : null,
                    'cost' => $log['cost'],
                    'sent_at' => $sentAt,
                    'delivered_at' => $delivered ? $sentAt->copy()->addSeconds(8) : null,
                    'failed_at' => $log['status'] === 'failed' ? $sentAt->copy()->addSeconds(12) : null,
                    'merchant_id' => $merchant?->id,
                    'entity_type' => 'merchant',
                    'entity_id' => $merchant?->id,
                    'created_at' => $sentAt,
                    'updated_at' => $sentAt,
                ]);
            }
        }

        $this->command?->info('Phase 12 templates: ' . NotificationTemplate::count());
        $this->command?->info('Phase 12 communication logs: ' . DB::table('communication_logs')->count());
    }
}
