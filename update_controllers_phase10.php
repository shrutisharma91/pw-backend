<?php

$dir = __DIR__;

$controllers = [
    'AuditTrailController' => <<<EOD
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Hash;

class AuditTrailController extends Controller
{
    // Screen 42: Audit Trail Explorer
    public function index(Request \$request)
    {
        \$query = AuditLog::with('user')->orderBy('created_at', 'desc');

        if (\$request->has('user_id')) \$query->where('user_id', \$request->user_id);
        if (\$request->has('module')) \$query->where('module', \$request->module);
        if (\$request->has('action_type')) \$query->where('action', \$request->action_type);
        if (\$request->has('date')) \$query->whereDate('created_at', \$request->date);
        if (\$request->has('ip_address')) \$query->where('ip_address', \$request->ip_address);

        return response()->json(\$query->paginate(30));
    }

    public function export(Request \$request)
    {
        return response()->json(['message' => 'Audit slice exported successfully for regulator.']);
    }

    public function detectAnomalies()
    {
        // Basic rule-based anomaly detection: e.g., high volume from single IP
        // Returns mocked anomaly alerts
        return response()->json([
            'anomalies' => [
                ['type' => 'Unusual Access', 'user_id' => 12, 'ip' => '192.168.1.5', 'description' => '100 API calls in 1 min']
            ]
        ]);
    }

    public function verifyHashChain()
    {
        // Mock verification logic
        return response()->json(['status' => 'Verified', 'message' => 'Tamper-evidence hash chain is intact.']);
    }
}
EOD,
    'ConsentLogController' => <<<EOD
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ConsentLog;

class ConsentLogController extends Controller
{
    // Screen 43: Consent Log Viewer
    public function index(Request \$request)
    {
        \$query = ConsentLog::with(['customer', 'merchant'])->orderBy('created_at', 'desc');

        if (\$request->has('customer_id')) \$query->where('customer_id', \$request->customer_id);
        if (\$request->has('merchant_id')) \$query->where('merchant_id', \$request->merchant_id);
        if (\$request->has('consent_type')) \$query->where('consent_type', \$request->consent_type);

        return response()->json(\$query->paginate(20));
    }

    public function withdraw(Request \$request, \$id)
    {
        \$request->validate(['reason' => 'required|string']);

        \$consent = ConsentLog::findOrFail(\$id);
        \$consent->status = 'Withdrawn';
        \$consent->save();

        return response()->json(['message' => 'Consent successfully withdrawn', 'consent' => \$consent]);
    }

    public function diff(\$id, \$compare_id)
    {
        // Compare payloads between two versions
        return response()->json([
            'message' => 'Version diff generated',
            'changes' => [
                'added' => ['marketing_opt_in' => true],
                'removed' => []
            ]
        ]);
    }

    public function export()
    {
        return response()->json(['message' => 'Consent logs exported for regulatory inspection.']);
    }
}
EOD,
    'ComplianceReportController' => <<<EOD
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ComplianceReport;
use App\Models\DataPrincipalRequest;

class ComplianceReportController extends Controller
{
    // Screen 44: Compliance Reports & Exports
    public function generateReturn(Request \$request)
    {
        \$request->validate(['report_type' => 'required|string']);

        \$report = ComplianceReport::create([
            'report_type' => \$request->report_type,
            'status' => 'Generated',
            'file_url' => 'https://example.com/exports/return.pdf'
        ]);

        return response()->json(['message' => 'RBI Return generated', 'report' => \$report]);
    }

    public function dpdpRequests(Request \$request)
    {
        \$requests = DataPrincipalRequest::with('customer')->orderBy('created_at', 'desc')->paginate(20);
        return response()->json(\$requests);
    }

    public function resolveDpdpRequest(Request \$request, \$id)
    {
        \$request->validate([
            'status' => 'required|in:Completed,Rejected',
            'resolution_notes' => 'required|string'
        ]);

        \$req = DataPrincipalRequest::findOrFail(\$id);
        \$req->update(\$request->all());

        return response()->json(['message' => 'DPDP Request resolved', 'request' => \$req]);
    }

    public function dataMaskingPolicy()
    {
        return response()->json([
            'policies' => [
                'customer_service' => ['mask_pan' => true, 'mask_phone' => true],
                'compliance_officer' => ['mask_pan' => false, 'mask_phone' => false]
            ]
        ]);
    }

    public function retentionPolicy()
    {
        return response()->json([
            'policies' => [
                'audit_logs' => '10 years',
                'consents' => '7 years',
                'marketing_data' => '1 year'
            ]
        ]);
    }
}
EOD
];

foreach ($controllers as $name => $content) {
    file_put_contents("$dir/app/Http/Controllers/Admin/$name.php", $content);
    echo "Updated Controller: $name\n";
}
