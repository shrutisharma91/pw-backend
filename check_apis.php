<?php

$expected = [
    "/api/admin/auth/login",
    "/api/admin/auth/mfa/verify",
    "/api/admin/auth/forgot-password",
    "/api/admin/profile",
    "/api/admin/users",
    "/api/admin/roles",
    "/api/admin/sessions",
    "/api/admin/merchants",
    "/api/admin/merchants/{id}/verification-logs",
    "/api/admin/merchants/{id}/agreement",
    "/api/admin/lenders",
    "/api/admin/lenders/{id}/sla",
    "/api/admin/waterfall",
    "/api/admin/waterfall/simulate",
    "/api/admin/lender-rules",
    "/api/admin/emi-config",
    "/api/admin/tenure-slabs",
    "/api/admin/offers",
    "/api/admin/offers/pending",
    "/api/admin/loans",
    "/api/admin/settlements",
    "/api/admin/collections",
    "/api/admin/fraud-alerts",
    "/api/admin/blacklist",
    "/api/admin/risk-rules",
    "/api/admin/review-queue",
    "/api/admin/audit-logs",
    "/api/admin/consent-logs",
    "/api/admin/compliance/export",
    "/api/admin/dpdp/request",
    "/api/admin/analytics/business",
    "/api/admin/analytics/lender",
    "/api/admin/analytics/sales",
    "/api/admin/reports/custom",
    "/api/admin/integrations",
    "/api/admin/feature-flags",
    "/api/admin/system/maintenance",
    "/api/admin/system/parameters",
    "/api/admin/templates",
    "/api/admin/communication-logs",
    "/api/admin/tickets"
];

$swagger = json_decode(file_get_contents('storage/api-docs/api-docs.json'), true);
$paths = array_keys($swagger['paths'] ?? []);

// Normalize by removing v1 and trailing slashes if any
$normalized_paths = array_map(function($path) {
    return str_replace('/api/v1/', '/api/', $path);
}, $paths);

$missing = [];
foreach ($expected as $exp) {
    $found = false;
    foreach ($normalized_paths as $np) {
        if (strpos($np, $exp) === 0 || strpos($exp, $np) === 0) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $missing[] = $exp;
    }
}

echo "Missing APIs:\n";
print_r($missing);
