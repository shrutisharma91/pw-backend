<?php

$tests = [
    'LoanApplicationTest' => [
        ['GET', '/api/v1/admin/loans', 200],
        ['GET', '/api/v1/admin/loans/export', 200],
        ['GET', '/api/v1/admin/loans/saved-filters', 200],
        ['POST', '/api/v1/admin/loans/saved-filters', 422], // expects name, payload
        ['GET', '/api/v1/admin/loans/1', 404],
        ['GET', '/api/v1/admin/loans/1/timeline', 200], // actually timeline is 200 if empty
        ['GET', '/api/v1/admin/loans/1/documents', 200], // 200 empty
        ['GET', '/api/v1/admin/loans/1/communications', 200], // 200 empty
    ],
    'ManualOverrideTest' => [
        ['POST', '/api/v1/admin/loans/overrides/1/force-approve', 422],
        ['POST', '/api/v1/admin/loans/overrides/1/override-rejection', 422],
        ['POST', '/api/v1/admin/loans/overrides/1/trigger-disbursal', 422],
        ['POST', '/api/v1/admin/loans/overrides/1/refund', 422],
    ],
    'DisbursalSettlementTest' => [
        ['GET', '/api/v1/admin/disbursals/pending', 200],
        ['POST', '/api/v1/admin/disbursals/trigger-batch', 422],
        ['GET', '/api/v1/admin/settlements/batches', 200],
        ['GET', '/api/v1/admin/settlements/batches/1/entries', 200],
        ['GET', '/api/v1/admin/settlements/batches/1/download', 200],
        ['POST', '/api/v1/admin/settlements/entries/1/dispute', 422],
    ],
    'CollectionTest' => [
        ['GET', '/api/v1/admin/collections', 200],
        ['POST', '/api/v1/admin/collections/1/assign-agent', 422],
        ['GET', '/api/v1/admin/collections/bounces', 200],
        ['POST', '/api/v1/admin/collections/bounces/1/retry', 404], // findOrFail
        ['POST', '/api/v1/admin/collections/1/npa-status', 422],
    ],
    'FraudAlertTest' => [
        ['GET', '/api/v1/admin/fraud-alerts', 200],
        ['POST', '/api/v1/admin/fraud-alerts/1/block', 404],
        ['POST', '/api/v1/admin/fraud-alerts/1/unblock', 404],
        ['POST', '/api/v1/admin/fraud-alerts/1/escalate', 404],
        ['GET', '/api/v1/admin/fraud-alerts/stats/heatmap', 200],
    ],
    'BlacklistTest' => [
        ['GET', '/api/v1/admin/blacklist', 200],
        ['POST', '/api/v1/admin/blacklist', 422],
        ['POST', '/api/v1/admin/blacklist/bulk-import', 422],
        ['POST', '/api/v1/admin/blacklist/1/remove', 422],
        ['POST', '/api/v1/admin/blacklist/1/whitelist-override', 422],
    ],
    'RiskRuleTest' => [
        ['GET', '/api/v1/admin/risk-rules', 200],
        ['POST', '/api/v1/admin/risk-rules', 422],
        ['PUT', '/api/v1/admin/risk-rules/1', 404],
        ['POST', '/api/v1/admin/risk-rules/simulate', 422],
    ],
    'ManualReviewTest' => [
        ['GET', '/api/v1/admin/manual-reviews', 200],
        ['GET', '/api/v1/admin/manual-reviews/1', 404],
        ['POST', '/api/v1/admin/manual-reviews/1/decide', 422],
        ['GET', '/api/v1/admin/manual-reviews/scorecard/1', 200],
    ],
    'AuditTrailTest' => [
        ['GET', '/api/v1/admin/audit-trails', 200],
        ['GET', '/api/v1/admin/audit-trails/export', 200],
        ['GET', '/api/v1/admin/audit-trails/anomalies', 200],
        ['POST', '/api/v1/admin/audit-trails/verify-hash', 200],
    ],
    'ConsentLogTest' => [
        ['GET', '/api/v1/admin/consents', 200],
        ['POST', '/api/v1/admin/consents/1/withdraw', 422],
        ['GET', '/api/v1/admin/consents/1/diff/2', 200],
        ['GET', '/api/v1/admin/consents/export', 200],
    ],
    'ComplianceReportTest' => [
        ['POST', '/api/v1/admin/compliance/returns', 422],
        ['GET', '/api/v1/admin/compliance/dpdp-requests', 200],
        ['POST', '/api/v1/admin/compliance/dpdp-requests/1/resolve', 422],
        ['GET', '/api/v1/admin/compliance/data-masking-policy', 200],
        ['POST', '/api/v1/admin/compliance/data-masking-policy', 200],
        ['GET', '/api/v1/admin/compliance/retention-policy', 200],
        ['POST', '/api/v1/admin/compliance/retention-policy', 200],
        ['GET', '/api/v1/admin/compliance/dashboard', 200],
    ]
];

$stub = <<<EOT
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
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

foreach ($tests as $class => $endpoints) {
    $methodsStr = "";
    foreach ($endpoints as $idx => $endpoint) {
        $httpMethod = strtolower($endpoint[0]);
        $url = $endpoint[1];
        $status = $endpoint[2];
        
        $methodName = str_replace(['/', '-'], '_', ltrim($url, '/api/v1/admin/'));
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
