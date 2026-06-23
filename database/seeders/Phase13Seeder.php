<?php

namespace Database\Seeders;

use App\Models\FeatureFlag;
use App\Models\Integration;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class Phase13Seeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'finzwork10@gmail.com')->first();
        $adminId = $admin?->id ?? 1;

        $this->seedWorkflows($adminId);
        $this->seedIntegrations($adminId);
        $this->seedFeatureFlags($adminId);
        $this->seedSystemParameters($adminId);
    }

    private function minimalCanvas(): array
    {
        return [
            'nodes' => [
                ['id' => 'n1', 'type' => 'start', 'label' => 'Start', 'x' => 100, 'y' => 50],
                ['id' => 'n2', 'type' => 'end', 'label' => 'End', 'x' => 100, 'y' => 200],
            ],
            'edges' => [
                ['from' => 'n1', 'to' => 'n2'],
            ],
        ];
    }

    private function merchantOnboardingCanvas(): array
    {
        return [
            'nodes' => [
                ['id' => 'n1', 'type' => 'start', 'label' => 'Merchant Submitted', 'x' => 100, 'y' => 50],
                ['id' => 'n2', 'type' => 'action', 'label' => 'KYC Auto-Verification', 'x' => 100, 'y' => 150],
                ['id' => 'n3', 'type' => 'decision', 'label' => 'KYC Pass?', 'x' => 100, 'y' => 250],
                ['id' => 'n4', 'type' => 'end', 'label' => 'Approved', 'x' => 250, 'y' => 350],
                ['id' => 'n5', 'type' => 'end', 'label' => 'Rejected', 'x' => 0, 'y' => 350],
            ],
            'edges' => [
                ['from' => 'n1', 'to' => 'n2'],
                ['from' => 'n2', 'to' => 'n3'],
                ['from' => 'n3', 'to' => 'n4', 'label' => 'Pass'],
                ['from' => 'n3', 'to' => 'n5', 'label' => 'Fail'],
            ],
        ];
    }

    private function seedWorkflows(int $adminId): void
    {
        $canvas = $this->minimalCanvas();

        // 1 — draft workflow (2 versions for rollback / versions API)
        $draft = Workflow::firstOrCreate(
            ['name' => 'Merchant Onboarding Flow'],
            [
                'workflow_type'  => 'merchant_onboarding',
                'description'    => 'Demo draft workflow for Phase 13 testing',
                'status'         => 'draft',
                'current_version'=> 2,
                'created_by'     => $adminId,
            ]
        );

        WorkflowVersion::firstOrCreate(
            ['workflow_id' => $draft->id, 'version_number' => 1],
            [
                'canvas'     => $this->merchantOnboardingCanvas(),
                'is_active'  => false,
                'created_by' => $adminId,
            ]
        );

        WorkflowVersion::firstOrCreate(
            ['workflow_id' => $draft->id, 'version_number' => 2],
            [
                'canvas'     => $canvas,
                'is_active'  => true,
                'created_by' => $adminId,
            ]
        );

        // 2 — published workflow
        $published = Workflow::firstOrCreate(
            ['name' => 'Offer Approval Flow'],
            [
                'workflow_type'  => 'offer_approval',
                'description'    => 'Published demo workflow',
                'status'         => 'published',
                'current_version'=> 1,
                'created_by'     => $adminId,
                'published_by'   => $adminId,
                'published_at'   => now()->subDays(3),
            ]
        );

        WorkflowVersion::firstOrCreate(
            ['workflow_id' => $published->id, 'version_number' => 1],
            [
                'canvas'     => $canvas,
                'is_active'  => true,
                'created_by' => $adminId,
            ]
        );

        // 3 — archived workflow
        $archived = Workflow::firstOrCreate(
            ['name' => 'Manual Override Flow'],
            [
                'workflow_type'  => 'manual_override',
                'description'    => 'Archived demo workflow',
                'status'         => 'archived',
                'current_version'=> 1,
                'created_by'     => $adminId,
            ]
        );

        WorkflowVersion::firstOrCreate(
            ['workflow_id' => $archived->id, 'version_number' => 1],
            [
                'canvas'     => $canvas,
                'is_active'  => true,
                'created_by' => $adminId,
            ]
        );
    }

    private function seedIntegrations(int $adminId): void
    {
        $integrations = [
            [
                'name'         => 'MSG91 SMS',
                'category'     => 'sms',
                'provider_key' => 'msg91',
                'base_url'     => 'https://api.msg91.com',
                'is_active'    => true,
                'is_primary'   => true,
                'is_fallback'  => false,
                'priority'     => 1,
            ],
            [
                'name'         => 'Kaleyra SMS',
                'category'     => 'sms',
                'provider_key' => 'kaleyra',
                'base_url'     => 'https://api.kaleyra.io',
                'is_active'    => true,
                'is_primary'   => false,
                'is_fallback'  => true,
                'priority'     => 2,
            ],
            [
                'name'         => 'AWS SES Email',
                'category'     => 'email',
                'provider_key' => 'ses',
                'base_url'     => 'https://email.us-east-1.amazonaws.com',
                'is_active'    => true,
                'is_primary'   => true,
                'is_fallback'  => false,
                'priority'     => 1,
            ],
            [
                'name'         => 'Karza GST',
                'category'     => 'gst',
                'provider_key' => 'karza_gst',
                'base_url'     => 'https://api.karza.in',
                'is_active'    => false,
                'is_primary'   => true,
                'is_fallback'  => false,
                'priority'     => 1,
            ],
            [
                'name'         => 'Cloudflare R2',
                'category'     => 'storage',
                'provider_key' => 'cloudflare_r2',
                'base_url'     => null,
                'is_active'    => true,
                'is_primary'   => true,
                'is_fallback'  => false,
                'priority'     => 1,
            ],
        ];

        foreach ($integrations as $data) {
            $integration = Integration::firstOrCreate(
                ['category' => $data['category'], 'provider_key' => $data['provider_key']],
                array_merge($data, [
                    'api_key_enc'    => Crypt::encryptString('demo_api_key_' . $data['provider_key']),
                    'api_secret_enc' => Crypt::encryptString('demo_secret_' . $data['provider_key']),
                    'timeout_seconds'=> 30,
                    'retry_attempts' => 2,
                    'notes'          => 'Seeded for Phase 13 API testing',
                ])
            );

            // Monthly call logs for billing summary
            if (! DB::table('integration_call_logs')->where('integration_id', $integration->id)->exists()) {
                DB::table('integration_call_logs')->insert([
                    [
                        'integration_id'   => $integration->id,
                        'endpoint'         => '/send',
                        'http_status'      => 200,
                        'response_time_ms' => 245,
                        'is_success'       => true,
                        'cost'             => 1.50,
                        'created_at'       => now()->subDays(2),
                        'updated_at'       => now()->subDays(2),
                    ],
                    [
                        'integration_id'   => $integration->id,
                        'endpoint'         => '/send',
                        'http_status'      => 200,
                        'response_time_ms' => 312,
                        'is_success'       => true,
                        'cost'             => 1.50,
                        'created_at'       => now()->subDay(),
                        'updated_at'       => now()->subDay(),
                    ],
                ]);
            }
        }
    }

    private function seedFeatureFlags(int $adminId): void
    {
        $checkoutFlag = FeatureFlag::firstOrCreate(
            ['key' => 'new_checkout_flow'],
            [
                'name'            => 'New Checkout Flow',
                'description'     => 'Gradual rollout of redesigned checkout',
                'type'            => 'boolean',
                'default_value'   => json_encode(false),
                'rollout_status'  => 'partial',
                'rollout_percent' => 25,
                'cohort_rules'    => json_encode(['merchant_tier' => 'gold', 'region' => 'MH']),
                'created_by'      => $adminId,
            ]
        );

        FeatureFlag::firstOrCreate(
            ['key' => 'express_kyc'],
            [
                'name'            => 'Express KYC',
                'description'     => 'Fast-track KYC for trusted merchants',
                'type'            => 'boolean',
                'default_value'   => json_encode(false),
                'rollout_status'  => 'on',
                'rollout_percent' => 100,
                'created_by'      => $adminId,
            ]
        );

        FeatureFlag::firstOrCreate(
            ['key' => 'beta_dashboard'],
            [
                'name'            => 'Beta Dashboard',
                'description'     => 'New analytics dashboard (kill-switch test candidate)',
                'type'            => 'boolean',
                'default_value'   => json_encode(false),
                'rollout_status'  => 'off',
                'rollout_percent' => 0,
                'created_by'      => $adminId,
            ]
        );

        // Active A/B test for new_checkout_flow (enables /ab-test/results)
        $existingTest = DB::table('ab_tests')
            ->where('flag_id', $checkoutFlag->id)
            ->where('status', 'active')
            ->first();

        if (! $existingTest) {
            $testId = DB::table('ab_tests')->insertGetId([
                'flag_id'         => $checkoutFlag->id,
                'name'            => 'Checkout Conversion Test',
                'variant_a_value' => json_encode(false),
                'variant_b_value' => json_encode(true),
                'traffic_split'   => 50,
                'metric'          => 'approval_rate',
                'status'          => 'active',
                'start_at'        => now()->subDays(7),
                'end_at'          => now()->addDays(30),
                'created_by'      => $adminId,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            // Seed events so results endpoint returns meaningful data
            $events = [];
            foreach (['A', 'B'] as $variant) {
                for ($i = 1; $i <= 120; $i++) {
                    $events[] = [
                        'test_id'    => $testId,
                        'variant'    => $variant,
                        'entity_id'  => $i,
                        'converted'  => $i % 5 === 0,
                        'event_at'   => now()->subHours(rand(1, 72)),
                    ];
                }
            }
            DB::table('ab_test_events')->insert($events);
        }

        // Audit trail for feature flag endpoints
        if (! DB::table('audit_logs')->where('payload->message', 'like', '%new_checkout_flow%')->exists()) {
            DB::table('audit_logs')->insert([
                'user_id'    => $adminId,
                'action'     => 'activity_log',
                'module'     => 'system',
                'ip_address' => '127.0.0.1',
                'payload'    => json_encode(['message' => "Feature flag updated: new_checkout_flow → status=partial, %=25"]),
                'created_at' => now()->subHour(),
            ]);
        }
    }

    private function seedSystemParameters(int $adminId): void
    {
        $parameters = [
            'otp_expiry_minutes'      => '10',
            'max_loan_amount'         => '500000',
            'min_loan_amount'         => '5000',
            'default_interest_rate'   => '14.5',
            'maintenance_mode'        => '0',
            'platform_name'           => 'FinZ LMS',
            'support_email'           => 'support@finz.com',
            'debug_logging_enabled'   => '0',
        ];

        foreach ($parameters as $key => $value) {
            DB::table('system_parameters')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_by' => $adminId, 'updated_at' => now()]
            );
        }

        if (! DB::table('audit_logs')->where('payload->message', 'like', 'System parameter updated%')->exists()) {
            DB::table('audit_logs')->insert([
                'user_id'    => $adminId,
                'action'     => 'activity_log',
                'module'     => 'system',
                'ip_address' => '127.0.0.1',
                'payload'    => json_encode([
                    'message'   => 'System parameter updated: otp_expiry_minutes',
                    'key'       => 'otp_expiry_minutes',
                    'old_value' => '5',
                    'new_value' => '10',
                ]),
                'created_at' => now()->subHours(2),
            ]);
        }
    }
}
