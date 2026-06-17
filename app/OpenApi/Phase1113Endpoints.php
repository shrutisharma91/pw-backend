<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class Phase1113Endpoints
{
    // ─── Phase 11 — Business Analytics ───────────────────────────────────────

    #[OA\Get(
        path: '/api/v1/admin/analytics/business',
        tags: ['Phase11-Analytics'],
        summary: 'Business analytics dashboard',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'period', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['7d', '30d', '90d', '1y', 'custom'], default: '30d')),
            new OA\Parameter(name: 'start_date', in: 'query', required: false, description: 'Required when period=custom', schema: new OA\Schema(type: 'string', format: 'date', example: '2026-01-01')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, description: 'Required when period=custom', schema: new OA\Schema(type: 'string', format: 'date', example: '2026-06-16')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function analyticsBusinessIndex(): void {}

    #[OA\Post(
        path: '/api/v1/admin/analytics/business/snapshot',
        tags: ['Phase11-Analytics'],
        summary: 'Save analytics snapshot',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'period'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', maxLength: 255, example: 'Board Snapshot June 2026'),
                    new OA\Property(property: 'period', type: 'string', enum: ['7d', '30d', '90d', '1y'], example: '30d'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Saved')]
    )]
    public function analyticsBusinessSnapshot(): void {}

    #[OA\Get(
        path: '/api/v1/admin/analytics/business/snapshots',
        tags: ['Phase11-Analytics'],
        summary: 'List analytics snapshots',
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function analyticsBusinessSnapshots(): void {}

    // ─── Phase 11 — Lender Analytics ─────────────────────────────────────────

    #[OA\Get(
        path: '/api/v1/admin/analytics/lender',
        tags: ['Phase11-Analytics'],
        summary: 'Lender analytics',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'period', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['7d', '30d', '90d', '1y'], default: '30d')),
            new OA\Parameter(name: 'lender_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function analyticsLenderIndex(): void {}

    #[OA\Get(
        path: '/api/v1/admin/analytics/lender/{id}/scorecard',
        tags: ['Phase11-Analytics'],
        summary: 'Lender scorecard',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function analyticsLenderScorecard(): void {}

    #[OA\Post(
        path: '/api/v1/admin/analytics/lender/export',
        tags: ['Phase11-Analytics'],
        summary: 'Export lender analytics',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['period', 'format'],
                properties: [
                    new OA\Property(property: 'period', type: 'string', enum: ['7d', '30d', '90d', '1y'], example: '30d'),
                    new OA\Property(property: 'format', type: 'string', enum: ['csv', 'xlsx', 'pdf'], example: 'csv'),
                    new OA\Property(property: 'lender_id', type: 'integer', nullable: true, example: 1),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Export started')]
    )]
    public function analyticsLenderExport(): void {}

    // ─── Phase 11 — Sales Analytics ──────────────────────────────────────────

    #[OA\Get(
        path: '/api/v1/admin/analytics/sales',
        tags: ['Phase11-Analytics'],
        summary: 'Sales analytics',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'period', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['7d', '30d', '90d', '1y'], default: '30d')),
            new OA\Parameter(name: 'region', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'Maharashtra')),
            new OA\Parameter(name: 'exec_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function analyticsSalesIndex(): void {}

    #[OA\Get(
        path: '/api/v1/admin/analytics/sales/region/{state}/stores',
        tags: ['Phase11-Analytics'],
        summary: 'Region stores drilldown',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'state', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'Maharashtra'))],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function analyticsSalesRegionStores(): void {}

    #[OA\Get(
        path: '/api/v1/admin/analytics/sales/exec/{id}/pipeline',
        tags: ['Phase11-Analytics'],
        summary: 'Executive pipeline',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function analyticsSalesExecPipeline(): void {}

    // ─── Phase 11 — Custom Reports ───────────────────────────────────────────

    #[OA\Get(path: '/api/v1/admin/reports/custom/schema', tags: ['Phase11-Reports'], summary: 'Custom report schema', security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function reportsCustomSchema(): void {}

    #[OA\Get(path: '/api/v1/admin/reports/custom', tags: ['Phase11-Reports'], summary: 'List custom reports', security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function reportsCustomIndex(): void {}

    #[OA\Post(
        path: '/api/v1/admin/reports/custom',
        tags: ['Phase11-Reports'],
        summary: 'Run custom report',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'module', 'fields'],
                example: [
                    'name'       => 'Disbursals by Merchant',
                    'module'     => 'loans',
                    'fields'     => ['loan_amount', 'status', 'disbursed_at'],
                    'chart_type' => 'table',
                    'limit'      => 100,
                ],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Disbursals by Merchant'),
                    new OA\Property(property: 'module', type: 'string', enum: ['loans', 'merchants', 'payments', 'stores', 'lenders'], example: 'loans'),
                    new OA\Property(property: 'fields', type: 'array', items: new OA\Items(type: 'string'), example: ['loan_amount', 'status', 'disbursed_at']),
                    new OA\Property(
                        property: 'filters',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'field', type: 'string', example: 'status'),
                                new OA\Property(property: 'operator', type: 'string', enum: ['=', '!=', '>', '<', '>=', '<=', 'like', 'in', 'between'], example: '='),
                                new OA\Property(property: 'value', example: 'disbursed'),
                            ],
                            type: 'object'
                        ),
                        example: [['field' => 'status', 'operator' => '=', 'value' => 'disbursed']]
                    ),
                    new OA\Property(property: 'group_by', type: 'array', items: new OA\Items(type: 'string'), example: ['status']),
                    new OA\Property(property: 'chart_type', type: 'string', enum: ['table', 'bar', 'line', 'pie', 'area', 'scatter'], example: 'table'),
                    new OA\Property(property: 'limit', type: 'integer', example: 100),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function reportsCustomRun(): void {}

    #[OA\Post(
        path: '/api/v1/admin/reports/custom/save',
        tags: ['Phase11-Reports'],
        summary: 'Save custom report',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'definition'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Monthly Disbursals'),
                    new OA\Property(property: 'definition', type: 'object', example: ['module' => 'loans', 'fields' => ['loan_amount', 'status']]),
                    new OA\Property(property: 'chart_type', type: 'string', example: 'table'),
                    new OA\Property(property: 'is_shared', type: 'boolean', example: false),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Saved')]
    )]
    public function reportsCustomSave(): void {}

    #[OA\Put(
        path: '/api/v1/admin/reports/custom/{id}',
        tags: ['Phase11-Reports'],
        summary: 'Update custom report',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Updated Report Name'),
                    new OA\Property(property: 'definition', type: 'object'),
                    new OA\Property(property: 'chart_type', type: 'string', example: 'bar'),
                    new OA\Property(property: 'is_shared', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function reportsCustomUpdate(): void {}

    #[OA\Post(
        path: '/api/v1/admin/reports/custom/{id}/schedule',
        tags: ['Phase11-Reports'],
        summary: 'Schedule custom report',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['frequency', 'recipients', 'format', 'time'],
                properties: [
                    new OA\Property(property: 'frequency', type: 'string', enum: ['daily', 'weekly', 'monthly'], example: 'weekly'),
                    new OA\Property(property: 'recipients', type: 'array', items: new OA\Items(type: 'string', format: 'email'), example: ['admin@example.com']),
                    new OA\Property(property: 'format', type: 'string', enum: ['csv', 'xlsx', 'pdf'], example: 'csv'),
                    new OA\Property(property: 'time', type: 'string', example: '09:00', description: 'HH:mm format'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Scheduled')]
    )]
    public function reportsCustomSchedule(): void {}

    #[OA\Post(
        path: '/api/v1/admin/reports/custom/{id}/export',
        tags: ['Phase11-Reports'],
        summary: 'Export custom report',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['format'],
                properties: [
                    new OA\Property(property: 'format', type: 'string', enum: ['csv', 'xlsx', 'pdf', 'json'], example: 'csv'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Export started')]
    )]
    public function reportsCustomExport(): void {}

    #[OA\Get(path: '/api/v1/admin/reports/custom/{id}/history', tags: ['Phase11-Reports'], summary: 'Custom report history', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function reportsCustomHistory(): void {}

    #[OA\Delete(path: '/api/v1/admin/reports/custom/{id}', tags: ['Phase11-Reports'], summary: 'Delete custom report', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Deleted')])]
    public function reportsCustomDelete(): void {}

    // ─── Phase 12 — Templates ────────────────────────────────────────────────

    #[OA\Get(path: '/api/v1/admin/templates/variables', tags: ['Phase12-Notifications'], summary: 'Template variables', security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function templatesVariables(): void {}

    #[OA\Get(
        path: '/api/v1/admin/templates',
        tags: ['Phase12-Notifications'],
        summary: 'List templates',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'channel', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['sms', 'email', 'whatsapp', 'push'])),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['draft', 'active', 'archived'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function templatesIndex(): void {}

    #[OA\Post(
        path: '/api/v1/admin/templates',
        tags: ['Phase12-Notifications'],
        summary: 'Create template',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'template_key', 'channel', 'body'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Loan Approved SMS'),
                    new OA\Property(property: 'template_key', type: 'string', example: 'loan_approved_sms'),
                    new OA\Property(property: 'channel', type: 'string', enum: ['sms', 'email', 'whatsapp', 'push'], example: 'sms'),
                    new OA\Property(property: 'subject', type: 'string', nullable: true, example: 'Loan Approved'),
                    new OA\Property(property: 'body', type: 'string', example: 'Hi {{customer_name}}, your loan of {{loan_amount}} is approved.'),
                    new OA\Property(property: 'variables', type: 'array', items: new OA\Items(type: 'string'), example: ['customer_name', 'loan_amount']),
                    new OA\Property(property: 'sender_id', type: 'string', nullable: true, example: 'FINZLM'),
                    new OA\Property(property: 'dlt_template_id', type: 'string', nullable: true),
                    new OA\Property(property: 'language', type: 'string', nullable: true, example: 'en'),
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]
    public function templatesStore(): void {}

    #[OA\Get(path: '/api/v1/admin/templates/{id}', tags: ['Phase12-Notifications'], summary: 'Template detail', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function templatesShow(): void {}

    #[OA\Put(
        path: '/api/v1/admin/templates/{id}',
        tags: ['Phase12-Notifications'],
        summary: 'Update template',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['body'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Loan Approved SMS v2'),
                    new OA\Property(property: 'subject', type: 'string', nullable: true),
                    new OA\Property(property: 'body', type: 'string', example: 'Hi {{customer_name}}, your loan is approved.'),
                    new OA\Property(property: 'variables', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'sender_id', type: 'string', nullable: true),
                    new OA\Property(property: 'dlt_template_id', type: 'string', nullable: true),
                    new OA\Property(property: 'language', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function templatesUpdate(): void {}

    #[OA\Post(path: '/api/v1/admin/templates/{id}/activate', tags: ['Phase12-Notifications'], summary: 'Activate template', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Activated')])]
    public function templatesActivate(): void {}

    #[OA\Post(path: '/api/v1/admin/templates/{id}/archive', tags: ['Phase12-Notifications'], summary: 'Archive template', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Archived')])]
    public function templatesArchive(): void {}

    #[OA\Post(path: '/api/v1/admin/templates/{id}/rollback/{version}', tags: ['Phase12-Notifications'], summary: 'Rollback template version', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'version', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Rolled back')])]
    public function templatesRollback(): void {}

    #[OA\Get(path: '/api/v1/admin/templates/{id}/diff/{v1}/{v2}', tags: ['Phase12-Notifications'], summary: 'Template version diff', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'v1', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'v2', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function templatesDiff(): void {}

    #[OA\Post(
        path: '/api/v1/admin/templates/{id}/test-send',
        tags: ['Phase12-Notifications'],
        summary: 'Test-send template',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['to'],
                properties: [
                    new OA\Property(property: 'to', type: 'string', example: '9876543210', description: 'Phone, email, or device token'),
                    new OA\Property(property: 'variables', type: 'object', example: ['customer_name' => 'Rahul', 'loan_amount' => '50000']),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Sent')]
    )]
    public function templatesTestSend(): void {}

    // ─── Phase 12 — Communication logs ───────────────────────────────────────

    #[OA\Get(
        path: '/api/v1/admin/communication-logs',
        tags: ['Phase12-Communications'],
        summary: 'Communication logs',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'channel', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['sms', 'email', 'whatsapp', 'push'])),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['sent', 'delivered', 'read', 'clicked', 'failed', 'bounced'])),
            new OA\Parameter(name: 'template_key', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'recipient', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'provider', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['msg91', 'ses', 'meta_wa', 'firebase'])),
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'merchant_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function communicationLogsIndex(): void {}

    #[OA\Get(
        path: '/api/v1/admin/communication-logs/stats/summary',
        tags: ['Phase12-Communications'],
        summary: 'Communication summary',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'period', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['7d', '30d', '90d'], default: '30d')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function communicationLogsSummary(): void {}

    #[OA\Get(
        path: '/api/v1/admin/communication-logs/stats/daily-trend',
        tags: ['Phase12-Communications'],
        summary: 'Communication daily trend',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'period', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['7d', '30d', '90d'], default: '30d')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function communicationLogsDailyTrend(): void {}

    #[OA\Get(path: '/api/v1/admin/communication-logs/{id}', tags: ['Phase12-Communications'], summary: 'Communication log detail', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function communicationLogsShow(): void {}

    #[OA\Post(
        path: '/api/v1/admin/communication-logs/resend',
        tags: ['Phase12-Communications'],
        summary: 'Resend failed messages',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['log_ids'],
                properties: [
                    new OA\Property(property: 'log_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3]),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Queued')]
    )]
    public function communicationLogsResend(): void {}

    // ─── Phase 12 — Documents ────────────────────────────────────────────────

    #[OA\Get(path: '/api/v1/admin/documents/stats', tags: ['Phase12-Documents'], summary: 'Document stats', security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function documentsStats(): void {}

    #[OA\Get(
        path: '/api/v1/admin/documents',
        tags: ['Phase12-Documents'],
        summary: 'List documents',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['kyc', 'agreement', 'invoice', 'statement', 'enach', 'esign', 'other'])),
            new OA\Parameter(name: 'entity_type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['merchant', 'customer', 'store'])),
            new OA\Parameter(name: 'entity_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending_ocr', 'ocr_done', 'virus_clean', 'quarantined', 'archived'])),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function documentsIndex(): void {}

    #[OA\Get(path: '/api/v1/admin/documents/{id}', tags: ['Phase12-Documents'], summary: 'Document detail', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function documentsShow(): void {}

    #[OA\Get(
        path: '/api/v1/admin/documents/{id}/preview',
        tags: ['Phase12-Documents'],
        summary: 'Preview document',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'redact_sensitive', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function documentsPreview(): void {}

    #[OA\Post(
        path: '/api/v1/admin/documents/{id}/share',
        tags: ['Phase12-Documents'],
        summary: 'Share document URL',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['expiry_minutes', 'purpose'],
                properties: [
                    new OA\Property(property: 'expiry_minutes', type: 'integer', minimum: 5, maximum: 1440, example: 60),
                    new OA\Property(property: 'purpose', type: 'string', example: 'Lender review'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function documentsShare(): void {}

    #[OA\Post(path: '/api/v1/admin/documents/{id}/ocr-rerun', tags: ['Phase12-Documents'], summary: 'Rerun OCR', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Queued')])]
    public function documentsRerunOcr(): void {}

    #[OA\Put(
        path: '/api/v1/admin/documents/{id}/retention',
        tags: ['Phase12-Documents'],
        summary: 'Update retention',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['retention_until'],
                properties: [
                    new OA\Property(property: 'retention_until', type: 'string', format: 'date', example: '2027-06-16'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function documentsRetention(): void {}

    #[OA\Delete(path: '/api/v1/admin/documents/{id}', tags: ['Phase12-Documents'], summary: 'Delete document', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Deleted')])]
    public function documentsDelete(): void {}

    // ─── Phase 13 — Workflows ────────────────────────────────────────────────

    #[OA\Get(path: '/api/v1/admin/workflows/templates', tags: ['Phase13-System'], summary: 'Workflow templates', security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function workflowsTemplates(): void {}

    #[OA\Get(
        path: '/api/v1/admin/workflows',
        tags: ['Phase13-System'],
        summary: 'List workflows',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['draft', 'published', 'archived'])),
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function workflowsIndex(): void {}

    #[OA\Post(
        path: '/api/v1/admin/workflows',
        tags: ['Phase13-System'],
        summary: 'Create workflow',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'workflow_type', 'canvas'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Loan Approval Flow'),
                    new OA\Property(property: 'workflow_type', type: 'string', example: 'loan_approval'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Standard loan approval workflow'),
                    new OA\Property(
                        property: 'canvas',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'nodes', type: 'array', items: new OA\Items(type: 'object')),
                            new OA\Property(property: 'edges', type: 'array', items: new OA\Items(type: 'object')),
                        ],
                        example: ['nodes' => [['id' => 'start', 'type' => 'start']], 'edges' => []]
                    ),
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]
    public function workflowsStore(): void {}

    #[OA\Get(path: '/api/v1/admin/workflows/{id}', tags: ['Phase13-System'], summary: 'Workflow detail', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function workflowsShow(): void {}

    #[OA\Put(
        path: '/api/v1/admin/workflows/{id}',
        tags: ['Phase13-System'],
        summary: 'Update workflow',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['canvas'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Loan Approval Flow v2'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(
                        property: 'canvas',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'nodes', type: 'array', items: new OA\Items(type: 'object')),
                            new OA\Property(property: 'edges', type: 'array', items: new OA\Items(type: 'object')),
                        ]
                    ),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function workflowsUpdate(): void {}

    #[OA\Post(path: '/api/v1/admin/workflows/{id}/publish', tags: ['Phase13-System'], summary: 'Publish workflow', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Published')])]
    public function workflowsPublish(): void {}

    #[OA\Post(path: '/api/v1/admin/workflows/{id}/archive', tags: ['Phase13-System'], summary: 'Archive workflow', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Archived')])]
    public function workflowsArchive(): void {}

    #[OA\Get(path: '/api/v1/admin/workflows/{id}/versions', tags: ['Phase13-System'], summary: 'Workflow versions', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function workflowsVersions(): void {}

    #[OA\Post(path: '/api/v1/admin/workflows/{id}/rollback/{version}', tags: ['Phase13-System'], summary: 'Rollback workflow version', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'version', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Rolled back')])]
    public function workflowsRollback(): void {}

    // ─── Phase 13 — Integrations ─────────────────────────────────────────────

    #[OA\Get(path: '/api/v1/admin/integrations/billing/summary', tags: ['Phase13-System'], summary: 'Integration billing summary', security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function integrationsBillingSummary(): void {}

    #[OA\Get(path: '/api/v1/admin/integrations', tags: ['Phase13-System'], summary: 'List integrations', security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function integrationsIndex(): void {}

    #[OA\Get(path: '/api/v1/admin/integrations/{id}', tags: ['Phase13-System'], summary: 'Integration detail', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function integrationsShow(): void {}

    #[OA\Put(
        path: '/api/v1/admin/integrations/{id}',
        tags: ['Phase13-System'],
        summary: 'Update integration config',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'base_url', type: 'string', format: 'uri', example: 'https://api.kaleyra.io'),
                    new OA\Property(property: 'api_key', type: 'string'),
                    new OA\Property(property: 'api_secret', type: 'string'),
                    new OA\Property(property: 'webhook_url', type: 'string', format: 'uri', nullable: true),
                    new OA\Property(property: 'is_active', type: 'boolean'),
                    new OA\Property(property: 'is_fallback', type: 'boolean'),
                    new OA\Property(property: 'priority', type: 'integer', minimum: 1, maximum: 10),
                    new OA\Property(property: 'timeout_seconds', type: 'integer', minimum: 1, maximum: 120),
                    new OA\Property(property: 'retry_attempts', type: 'integer', minimum: 0, maximum: 5),
                    new OA\Property(property: 'notes', type: 'string', maxLength: 500),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function integrationsUpdate(): void {}

    #[OA\Post(path: '/api/v1/admin/integrations/{id}/toggle', tags: ['Phase13-System'], summary: 'Toggle integration', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Updated')])]
    public function integrationsToggle(): void {}

    #[OA\Post(path: '/api/v1/admin/integrations/{id}/health-check', tags: ['Phase13-System'], summary: 'Integration health check', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function integrationsHealthCheck(): void {}

    #[OA\Post(path: '/api/v1/admin/integrations/health-check-all', tags: ['Phase13-System'], summary: 'Health check all integrations', security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function integrationsHealthCheckAll(): void {}

    #[OA\Put(
        path: '/api/v1/admin/integrations/category/{category}/primary',
        tags: ['Phase13-System'],
        summary: 'Set primary integration by category',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'sms'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['provider_id'],
                properties: [
                    new OA\Property(property: 'provider_id', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function integrationsSetPrimary(): void {}

    // ─── Phase 13 — Feature Flags ────────────────────────────────────────────

    #[OA\Get(
        path: '/api/v1/admin/feature-flags',
        tags: ['Phase13-System'],
        summary: 'List feature flags',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['on', 'off', 'partial'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function featureFlagsIndex(): void {}

    #[OA\Post(
        path: '/api/v1/admin/feature-flags',
        tags: ['Phase13-System'],
        summary: 'Create feature flag',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'key', 'type', 'default_value'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'New Checkout Flow'),
                    new OA\Property(property: 'key', type: 'string', example: 'new_checkout_flow'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'type', type: 'string', enum: ['boolean', 'percentage', 'cohort'], example: 'boolean'),
                    new OA\Property(property: 'default_value', example: false),
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]
    public function featureFlagsStore(): void {}

    #[OA\Get(path: '/api/v1/admin/feature-flags/{key}', tags: ['Phase13-System'], summary: 'Feature flag detail', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'key', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function featureFlagsShow(): void {}

    #[OA\Put(
        path: '/api/v1/admin/feature-flags/{key}',
        tags: ['Phase13-System'],
        summary: 'Update feature flag',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'key', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'rollout_status', type: 'string', enum: ['on', 'off', 'partial'], example: 'partial'),
                    new OA\Property(property: 'rollout_percent', type: 'integer', minimum: 0, maximum: 100, example: 25),
                    new OA\Property(property: 'cohort_rules', type: 'object', properties: [
                        new OA\Property(property: 'merchant_tier', type: 'string', enum: ['bronze', 'silver', 'gold']),
                        new OA\Property(property: 'region', type: 'string'),
                        new OA\Property(property: 'signup_after', type: 'string', format: 'date'),
                    ]),
                    new OA\Property(property: 'description', type: 'string'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function featureFlagsUpdate(): void {}

    #[OA\Post(
        path: '/api/v1/admin/feature-flags/{key}/kill',
        tags: ['Phase13-System'],
        summary: 'Kill switch feature flag',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'key', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['reason'],
                properties: [
                    new OA\Property(property: 'reason', type: 'string', example: 'Causing checkout failures in production'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Killed')]
    )]
    public function featureFlagsKill(): void {}

    #[OA\Post(
        path: '/api/v1/admin/feature-flags/{key}/ab-test',
        tags: ['Phase13-System'],
        summary: 'Create A/B test for flag',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'key', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'variant_a_value', 'variant_b_value', 'traffic_split', 'metric', 'start_at', 'end_at'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Checkout A/B Test'),
                    new OA\Property(property: 'variant_a_value', example: false),
                    new OA\Property(property: 'variant_b_value', example: true),
                    new OA\Property(property: 'traffic_split', type: 'integer', minimum: 1, maximum: 99, example: 50, description: 'Percent going to variant B'),
                    new OA\Property(property: 'metric', type: 'string', example: 'approval_rate'),
                    new OA\Property(property: 'start_at', type: 'string', format: 'date-time', example: '2026-06-17T00:00:00Z'),
                    new OA\Property(property: 'end_at', type: 'string', format: 'date-time', example: '2026-07-17T00:00:00Z'),
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]
    public function featureFlagsAbTest(): void {}

    #[OA\Get(path: '/api/v1/admin/feature-flags/{key}/ab-test/results', tags: ['Phase13-System'], summary: 'A/B test results', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'key', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function featureFlagsAbTestResults(): void {}

    #[OA\Get(path: '/api/v1/admin/feature-flags/{key}/audit', tags: ['Phase13-System'], summary: 'Feature flag audit', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'key', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function featureFlagsAudit(): void {}

    // ─── Phase 13 — System Parameters ────────────────────────────────────────

    #[OA\Get(path: '/api/v1/admin/system/parameters', tags: ['Phase13-System'], summary: 'List system parameters', security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function systemParametersIndex(): void {}

    #[OA\Get(
        path: '/api/v1/admin/system/parameters/audit',
        tags: ['Phase13-System'],
        summary: 'System parameter audit logs',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'key', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function systemParametersAudit(): void {}

    #[OA\Get(path: '/api/v1/admin/system/parameters/{key}', tags: ['Phase13-System'], summary: 'System parameter detail', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'key', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function systemParametersShow(): void {}

    #[OA\Put(
        path: '/api/v1/admin/system/parameters',
        tags: ['Phase13-System'],
        summary: 'Update system parameters',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['parameters'],
                properties: [
                    new OA\Property(
                        property: 'parameters',
                        type: 'array',
                        items: new OA\Items(
                            required: ['key', 'value'],
                            properties: [
                                new OA\Property(property: 'key', type: 'string', example: 'max_loan_amount'),
                                new OA\Property(property: 'value', example: 500000),
                            ],
                            type: 'object'
                        ),
                        example: [['key' => 'max_loan_amount', 'value' => 500000]]
                    ),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function systemParametersUpdate(): void {}

    #[OA\Post(
        path: '/api/v1/admin/system/maintenance',
        tags: ['Phase13-System'],
        summary: 'Toggle maintenance mode',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['enabled'],
                properties: [
                    new OA\Property(property: 'enabled', type: 'boolean', example: false),
                    new OA\Property(property: 'banner', type: 'string', nullable: true, example: 'Scheduled maintenance tonight 11pm–1am'),
                    new OA\Property(property: 'ends_at', type: 'string', format: 'date-time', nullable: true, example: '2026-06-17T01:00:00Z'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function systemMaintenanceToggle(): void {}
}
