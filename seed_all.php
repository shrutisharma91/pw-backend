<?php

use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Store;
use App\Models\Lender;
use App\Models\EmiType;
use App\Models\LoanApplication;
use App\Models\Disbursal;
use App\Models\SettlementBatch;
use App\Models\SettlementEntry;
use App\Models\Collection;
use App\Models\BounceEvent;
use App\Models\FraudAlert;
use App\Models\BlacklistEntry;
use App\Models\RiskRule;
use App\Models\ManualReview;
use App\Models\AuditLog;
use App\Models\ConsentLog;
use App\Models\DataPrincipalRequest;
use Illuminate\Support\Str;

echo "Seeding basic setup...\n";
$c = Customer::firstOrCreate(['phone' => '1234567890'], ['name' => 'Alice', 'email' => 'alice@example.com', 'pan_number' => 'ALICE1234F']);
$m = Merchant::firstOrCreate(['business_name' => 'Tech Superstore']);
$s = Store::firstOrCreate(['merchant_id' => $m->id], ['name' => 'Tech Superstore Downtown']);
$l = Lender::firstOrCreate(['name' => 'Global Finance']);
$e = EmiType::firstOrCreate(['name' => 'No Cost EMI'], ['type' => 'no-cost']);

echo "Seeding Loans...\n";
$loan1 = LoanApplication::firstOrCreate(['id' => 100], [
    'customer_id' => $c->id,
    'merchant_id' => $m->id,
    'store_id' => $s->id,
    'lender_id' => $l->id,
    'amount' => 85000,
    'emi_type_id' => $e->id,
    'status' => 'Pending',
    'sla_breached' => false
]);

$loan2 = LoanApplication::firstOrCreate(['id' => 101], [
    'customer_id' => $c->id,
    'merchant_id' => $m->id,
    'store_id' => $s->id,
    'lender_id' => $l->id,
    'amount' => 120000,
    'emi_type_id' => $e->id,
    'status' => 'Approved',
    'sla_breached' => true
]);

echo "Seeding Disbursals & Settlements...\n";
Disbursal::firstOrCreate(['loan_application_id' => $loan1->id], ['lender_id' => $l->id, 'amount' => 85000, 'status' => 'Pending']);
$batch = SettlementBatch::firstOrCreate(['id' => 1], ['lender_id' => $l->id, 'date' => now(), 'total_amount' => 120000, 'status' => 'Processing']);
SettlementEntry::firstOrCreate(['id' => 1], ['settlement_batch_id' => $batch->id, 'merchant_id' => $m->id, 'loan_application_id' => $loan2->id, 'gross' => 120000, 'fees' => 1000, 'net' => 119000, 'status' => 'cleared']);

echo "Seeding Collections...\n";
$collection = Collection::firstOrCreate(['id' => 1], ['loan_application_id' => $loan2->id, 'agent_id' => 1, 'dpd_bucket' => '30-60', 'overdue_amount' => 15000, 'npa_status' => null, 'status' => 'Open']);
BounceEvent::firstOrCreate(['id' => 1], ['collection_id' => $collection->id, 'date' => now(), 'amount' => 15000, 'reason' => 'Insufficient Funds', 'auto_retry_status' => 'Pending']);

echo "Seeding Fraud Alerts...\n";
FraudAlert::firstOrCreate(['id' => 1], ['customer_id' => $c->id, 'merchant_id' => $m->id, 'signal_type' => 'Velocity', 'severity' => 'High', 'description' => 'Multiple loans in 1 hour', 'status' => 'Open']);

echo "Seeding Blacklist...\n";
BlacklistEntry::firstOrCreate(['id' => 1], ['category' => 'PAN', 'value' => 'ALICE1234F', 'reason' => 'Suspicious Activity', 'severity' => 'High', 'status' => 'Active']);

echo "Seeding Risk Rules...\n";
RiskRule::firstOrCreate(['id' => 1], ['rule_type' => 'velocity', 'name' => 'High Velocity Block', 'parameters' => ['count' => 5], 'threshold' => 1.0, 'action' => 'block']);

echo "Seeding Manual Reviews...\n";
ManualReview::firstOrCreate(['id' => 1], ['loan_application_id' => $loan1->id, 'assigned_to' => 1, 'risk_score' => 75.5, 'status' => 'Pending', 'sla_deadline' => now()->addHours(24), 'sla_breached' => false]);

echo "Seeding Audit Trails...\n";
AuditLog::firstOrCreate(['id' => 1], ['user_id' => 1, 'action' => 'Force Approve', 'module' => 'Loans', 'details' => ['loan_id' => 101], 'ip_address' => '127.0.0.1', 'device_info' => 'Chrome Windows']);

echo "Seeding Consent Logs...\n";
ConsentLog::firstOrCreate(['id' => 1], ['customer_id' => $c->id, 'merchant_id' => $m->id, 'consent_type' => 'Terms & Conditions', 'version' => '1.0', 'ip_address' => '127.0.0.1', 'payload' => ['accepted' => true], 'status' => 'Active']);

echo "Seeding DPDP Requests...\n";
DataPrincipalRequest::firstOrCreate(['id' => 1], ['customer_id' => $c->id, 'request_type' => 'Data Erasure', 'status' => 'Pending']);

echo "Seeding Complete!\n";
