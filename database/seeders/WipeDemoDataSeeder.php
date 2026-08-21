<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class WipeDemoDataSeeder extends Seeder
{
    /**
     * Remove all seeded business data for phases 2–14 while keeping Super Admin login + RBAC.
     */
    public function run(): void
    {
        $keepEmail = 'finzwork10@gmail.com';
        $admin = User::where('email', $keepEmail)->first();

        if (! $admin) {
            throw new \RuntimeException("Cannot wipe: Super Admin {$keepEmail} was not found.");
        }

        $keepUserId = $admin->id;
        $builtinRoles = config('rbac.builtin_roles', ['superadmin']);

        Schema::disableForeignKeyConstraints();

        foreach ($this->tablesToEmpty() as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        if (Schema::hasTable('sqlite_sequence')) {
            DB::table('sqlite_sequence')->whereIn('name', $this->tablesToEmpty())->delete();
        }

        User::query()->where('id', '!=', $keepUserId)->delete();

        if (Schema::hasTable('model_has_roles')) {
            DB::table('model_has_roles')->where('model_id', '!=', $keepUserId)->delete();
        }

        if (Schema::hasTable('model_has_permissions')) {
            DB::table('model_has_permissions')->where('model_id', '!=', $keepUserId)->delete();
        }

        Role::query()
            ->whereNotIn('name', $builtinRoles)
            ->each(function (Role $role) {
                $role->delete();
            });

        if (Schema::hasTable('role_configs')) {
            DB::table('role_configs')->whereNotIn('name', $builtinRoles)->delete();
        }

        Schema::enableForeignKeyConstraints();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $admin->refresh();
        $admin->failed_login_attempts = 0;
        $admin->locked_until = null;
        $admin->save();

        if (! $admin->hasRole('superadmin')) {
            $admin->assignRole('superadmin');
        }

        $this->command?->info("Kept Super Admin {$keepEmail} (id {$keepUserId}). Wiped phase 2–14 demo data.");
        $this->command?->info('Users remaining: '.User::count());
    }

    /**
     * @return list<string>
     */
    private function tablesToEmpty(): array
    {
        return [
            'ab_test_events',
            'ab_tests',
            'admin_notifications',
            'admin_sessions',
            'agent_incentives',
            'agent_store_visits',
            'agent_targets',
            'analytics_snapshots',
            'audit_logs',
            'blacklist_entries',
            'bounce_events',
            'brands',
            'cache',
            'cache_locks',
            'categories',
            'collections',
            'communication_logs',
            'compliance_reports',
            'consent_logs',
            'custom_report_versions',
            'custom_reports',
            'customer_otps',
            'customers',
            'data_principal_requests',
            'disbursals',
            'document_shares',
            'document_versions',
            'documents',
            'emi_types',
            'failed_jobs',
            'feature_flags',
            'fraud_alerts',
            'integration_call_logs',
            'integrations',
            'job_batches',
            'jobs',
            'lender_api_logs',
            'lender_api_stats',
            'lender_commissions',
            'lender_rules',
            'lender_sla_logs',
            'lender_waterfalls',
            'lenders',
            'loan_applications',
            'loan_communications',
            'loan_documents',
            'loan_installments',
            'loan_rejection_logs',
            'loan_timeline_events',
            'loans',
            'manual_reviews',
            'merchant_agreements',
            'merchant_audit_logs',
            'merchant_categories',
            'merchant_notes',
            'merchants',
            'notification_template_versions',
            'notification_templates',
            'offers',
            'password_histories',
            'password_reset_tokens',
            'payments',
            'pos_terminals',
            'product_store',
            'products',
            'report_schedules',
            'risk_rules',
            'saved_filters',
            'sessions',
            'settlement_batches',
            'settlement_disputes',
            'settlement_entries',
            'stores',
            'subvention_matrices',
            'subvention_records',
            'suspension_reasons',
            'system_parameters',
            'tenure_slabs',
            'ticket_attachments',
            'ticket_links',
            'ticket_messages',
            'tickets',
            'verification_logs',
            'workflow_versions',
            'workflows',
        ];
    }
}
