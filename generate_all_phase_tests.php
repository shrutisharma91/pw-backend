<?php

$phases = [
    'Phase01AuthTest' => [
        ['POST', '/api/v1/auth/login', 422], 
        ['POST', '/api/v1/auth/mfa/verify', 422],
        ['POST', '/api/v1/auth/forgot-password', 422],
        ['POST', '/api/v1/auth/reset-password', 422],
        ['GET', '/api/v1/admin/profile', 200],
        ['PUT', '/api/v1/admin/profile', 200],
    ],
    'Phase03UserManagementTest' => [
        ['GET', '/api/v1/admin/users', 200],
        ['POST', '/api/v1/admin/users', 422],
        ['GET', '/api/v1/admin/users/1', 404],
        ['PUT', '/api/v1/admin/users/1', 404],
        ['POST', '/api/v1/admin/users/1/disable', 404],
        ['GET', '/api/v1/admin/roles', 200],
        ['POST', '/api/v1/admin/roles', 422],
        ['PUT', '/api/v1/admin/permissions/roles/1', 404],
        ['GET', '/api/v1/admin/sessions', 200],
        ['POST', '/api/v1/admin/sessions/1/revoke', 404],
    ],
    'Phase04MerchantLifecycleTest' => [
        ['GET', '/api/v1/admin/merchants', 200],
        ['GET', '/api/v1/admin/merchants/1', 404],
        ['POST', '/api/v1/admin/merchants/1/approve', 404],
        ['POST', '/api/v1/admin/merchants/1/reject', 422], // Validation failed for reason
        ['POST', '/api/v1/admin/merchants/1/re-kyc', 404],
        ['POST', '/api/v1/admin/merchants/1/suspend', 404],
        ['GET', '/api/v1/admin/merchants/1/verification-logs', 404],
        ['GET', '/api/v1/admin/merchants/1/agreement', 404],
    ],
    'Phase05StoreAndProductTest' => [
        ['GET', '/api/v1/admin/stores', 200],
        ['GET', '/api/v1/admin/stores/1', 404],
        ['GET', '/api/v1/admin/products', 200],
        ['GET', '/api/v1/admin/categories', 200],
        ['GET', '/api/v1/admin/brands', 200],
    ],
    'Phase06LenderOperationsTest' => [
        ['GET', '/api/v1/admin/lenders', 200],
        ['POST', '/api/v1/admin/lenders', 422],
        ['GET', '/api/v1/admin/lenders/1', 404],
        ['GET', '/api/v1/admin/lender-sla/metrics', 200],
        ['GET', '/api/v1/admin/lender-waterfalls', 200],
        ['POST', '/api/v1/admin/lender-waterfalls/simulate', 422],
        ['GET', '/api/v1/admin/lender-rules', 200],
    ],
    'Phase07PricingAndOffersTest' => [
        ['GET', '/api/v1/admin/pricing/emi-types', 200],
        ['GET', '/api/v1/admin/pricing/tenure-slabs', 200],
        ['GET', '/api/v1/admin/offers', 200],
        ['POST', '/api/v1/admin/offers', 422],
        ['GET', '/api/v1/admin/offers/pending', 200],
        ['POST', '/api/v1/admin/offers/1/approve', 404],
    ],
    'Phase08LoanAndDisbursalTest' => [
        ['GET', '/api/v1/admin/loans', 200],
        ['GET', '/api/v1/admin/loans/1', 404],
        ['POST', '/api/v1/admin/loans/overrides/1/force-approve', 422],
        ['POST', '/api/v1/admin/loans/overrides/1/trigger-disbursal', 422],
        ['GET', '/api/v1/admin/settlements/batches', 200],
        ['GET', '/api/v1/admin/collections', 200],
    ],
    'Phase09RiskAndFraudTest' => [
        ['GET', '/api/v1/admin/fraud-alerts', 200],
        ['GET', '/api/v1/admin/blacklist', 200],
        ['POST', '/api/v1/admin/blacklist', 422],
        ['POST', '/api/v1/admin/blacklist/1/remove', 422],
        ['GET', '/api/v1/admin/risk-rules', 200],
        ['GET', '/api/v1/admin/manual-reviews', 200],
    ],
    'Phase10ComplianceAndAuditTest' => [
        ['GET', '/api/v1/admin/audit-trails', 200],
        ['GET', '/api/v1/admin/consents', 200],
        ['POST', '/api/v1/admin/compliance/returns', 422],
        ['GET', '/api/v1/admin/compliance/dpdp-requests', 200],
    ],
    'Phase11AnalyticsAndBITest' => [
        ['GET', '/api/v1/admin/analytics/business', 200],
        ['GET', '/api/v1/admin/analytics/lender', 200],
        ['GET', '/api/v1/admin/analytics/sales', 200],
        ['POST', '/api/v1/admin/reports/custom', 422],
    ],
    'Phase12NotificationsAndDocsTest' => [
        ['GET', '/api/v1/admin/templates', 200],
        ['GET', '/api/v1/admin/communication-logs', 200],
        ['GET', '/api/v1/admin/documents', 200],
    ],
    'Phase13SystemAndIntegrationsTest' => [
        ['GET', '/api/v1/admin/workflows', 200],
        ['GET', '/api/v1/admin/integrations', 200],
        ['GET', '/api/v1/admin/feature-flags', 200],
        ['GET', '/api/v1/admin/system/parameters', 200],
        ['PUT', '/api/v1/admin/system/maintenance', 200],
    ],
    'Phase14SupportAndHelpdeskTest' => [
        ['GET', '/api/v1/admin/tickets', 200],
        ['GET', '/api/v1/admin/tickets/1', 404],
        ['POST', '/api/v1/admin/tickets/1/resolve', 404],
    ]
];

$stub = <<<EOT
<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class {ClassName} extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \$this->withoutMiddleware();
        \$this->user = User::factory()->create([
            'mfa_verified_at' => now(),
        ]);
    }

{Methods}
}
EOT;

$methodStub = <<<EOT
    public function test_{methodName}()
    {
        \$response = \$this->actingAs(\$this->user)->{httpMethod}('{url}');
        \$response->assertStatus({status});
    }
EOT;

@mkdir(__DIR__ . '/tests/Feature/Api', 0777, true);

foreach ($phases as $class => $endpoints) {
    $methodsStr = "";
    foreach ($endpoints as $idx => $endpoint) {
        $httpMethod = strtolower($endpoint[0]);
        $url = $endpoint[1];
        $status = $endpoint[2];
        
        $methodName = str_replace(['/', '-'], '_', ltrim($url, '/api/v1/'));
        $methodName = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $methodName));
        $methodName = $httpMethod . "_" . $methodName . "_" . $idx;
        
        $m = str_replace(
            ['{methodName}', '{httpMethod}', '{url}', '{status}'],
            [$methodName, $httpMethod, $url, $status],
            $methodStub
        );
        $methodsStr .= $m . "\n\n";
    }
    
    $content = str_replace(
        ['{ClassName}', '{Methods}'],
        [$class, $methodsStr],
        $stub
    );
    
    file_put_contents(__DIR__ . '/tests/Feature/Api/' . $class . '.php', $content);
    echo "Generated $class\n";
}

// Generate the markdown artifact payload
$markdown = "# FinZ LMS - Phase API Mapping\n\nThis document maps the implementation phases to their core API endpoints for manual testing purposes.\n\n";

foreach ($phases as $phaseName => $endpoints) {
    // Format Phase Name
    $prettyPhase = preg_replace('/(Phase[0-9]+)(.*)Test/', '$1: $2', $phaseName);
    $markdown .= "## $prettyPhase\n\n";
    $markdown .= "| Method | Endpoint | Expected Status (Unauthenticated/Validation) |\n";
    $markdown .= "|--------|----------|-----------------|\n";
    foreach ($endpoints as $endpoint) {
        $markdown .= "| `{$endpoint[0]}` | `{$endpoint[1]}` | `{$endpoint[2]}` |\n";
    }
    $markdown .= "\n";
}

file_put_contents(__DIR__ . '/phase_api_mapping_content.txt', $markdown);
echo "\nMarkdown generated in phase_api_mapping_content.txt\n";
