<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class Phase14Endpoints
{
    // ─── Phase 14 — Master Ticket Queue (Screen 56) ──────────────────────────

    #[OA\Get(
        path: '/api/v1/admin/tickets',
        tags: ['Phase14-Support'],
        summary: 'Master ticket queue',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'source_role', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['merchant', 'customer', 'store', 'lender_ops', 'internal'])),
            new OA\Parameter(name: 'category', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['dispute', 'complaint', 'technical', 'billing', 'kyc', 'loan', 'settlement', 'agreement', 'other'])),
            new OA\Parameter(name: 'priority', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['critical', 'high', 'medium', 'low'])),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['open', 'in_progress', 'waiting', 'resolved', 'closed', 'escalated'])),
            new OA\Parameter(name: 'sla_state', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['ok', 'at_risk', 'breached'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function ticketsIndex(): void {}

    #[OA\Post(
        path: '/api/v1/admin/tickets',
        tags: ['Phase14-Support'],
        summary: 'Create support ticket',
        description: 'Create a ticket for the master queue. Status defaults to open (or in_progress when assigned). Supports optional file attachments via multipart/form-data.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                'application/json' => new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        required: ['subject', 'description'],
                        properties: [
                            new OA\Property(property: 'subject', type: 'string', example: 'Settlement amount mismatch'),
                            new OA\Property(property: 'description', type: 'string', example: 'Merchant reports settlement batch total does not match dashboard.'),
                            new OA\Property(property: 'priority', type: 'string', enum: ['critical', 'high', 'medium', 'low'], example: 'high'),
                            new OA\Property(property: 'category', type: 'string', enum: ['dispute', 'complaint', 'technical', 'billing', 'kyc', 'loan', 'settlement', 'agreement', 'other'], example: 'settlement'),
                            new OA\Property(property: 'assigned_to', type: 'integer', nullable: true, example: 1),
                            new OA\Property(property: 'source_role', type: 'string', enum: ['merchant', 'customer', 'store', 'lender_ops', 'internal'], example: 'merchant'),
                            new OA\Property(property: 'reporter_name', type: 'string', example: 'Tech Superstore'),
                            new OA\Property(property: 'reporter_email', type: 'string', format: 'email', example: 'ops@techsuperstore.com'),
                            new OA\Property(property: 'reporter_phone', type: 'string', nullable: true, example: '9876543210'),
                            new OA\Property(property: 'entity_type', type: 'string', example: 'merchant'),
                            new OA\Property(property: 'entity_id', type: 'integer', example: 1),
                        ]
                    )
                ),
                'multipart/form-data' => new OA\MediaType(
                    mediaType: 'multipart/form-data',
                    schema: new OA\Schema(
                        required: ['subject', 'description'],
                        properties: [
                            new OA\Property(property: 'subject', type: 'string'),
                            new OA\Property(property: 'description', type: 'string'),
                            new OA\Property(property: 'priority', type: 'string'),
                            new OA\Property(property: 'category', type: 'string'),
                            new OA\Property(property: 'assigned_to', type: 'integer'),
                            new OA\Property(
                                property: 'attachments',
                                type: 'array',
                                items: new OA\Items(type: 'string', format: 'binary')
                            ),
                        ]
                    )
                ),
            ]
        ),
        responses: [
            new OA\Response(response: 201, description: 'Ticket created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function ticketsStore(): void {}

    #[OA\Get(path: '/api/v1/admin/tickets/stats', tags: ['Phase14-Support'], summary: 'Ticket queue stats', security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function ticketsStats(): void {}

    #[OA\Post(
        path: '/api/v1/admin/tickets/bulk',
        tags: ['Phase14-Support'],
        summary: 'Bulk ticket actions',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['action', 'ticket_ids'],
                properties: [
                    new OA\Property(property: 'action', type: 'string', enum: ['reassign', 'close', 'escalate']),
                    new OA\Property(property: 'ticket_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2]),
                    new OA\Property(property: 'assignee_id', type: 'integer', example: 1),
                    new OA\Property(property: 'escalate_to', type: 'integer', example: 1),
                    new OA\Property(property: 'note', type: 'string'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function ticketsBulk(): void {}

    // ─── Phase 14 — Ticket Detail & SLA (Screen 57) ──────────────────────────

    #[OA\Get(path: '/api/v1/admin/tickets/{id}', tags: ['Phase14-Support'], summary: 'Ticket detail', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function ticketsShow(): void {}

    #[OA\Put(
        path: '/api/v1/admin/tickets/{id}',
        tags: ['Phase14-Support'],
        summary: 'Update ticket',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['open', 'in_progress', 'waiting', 'resolved', 'closed', 'escalated']),
                    new OA\Property(property: 'priority', type: 'string', enum: ['critical', 'high', 'medium', 'low']),
                    new OA\Property(property: 'category', type: 'string'),
                    new OA\Property(property: 'assigned_to', type: 'integer', nullable: true),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function ticketsUpdate(): void {}

    #[OA\Get(path: '/api/v1/admin/tickets/{id}/sla', tags: ['Phase14-Support'], summary: 'Ticket SLA tracking', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function ticketsSla(): void {}

    #[OA\Post(
        path: '/api/v1/admin/tickets/{id}/messages',
        tags: ['Phase14-Support'],
        summary: 'Add ticket message',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['body', 'visibility'],
                properties: [
                    new OA\Property(property: 'body', type: 'string', example: 'We are reviewing your settlement batch.'),
                    new OA\Property(property: 'visibility', type: 'string', enum: ['public', 'internal'], example: 'public'),
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]
    public function ticketsAddMessage(): void {}

    #[OA\Post(
        path: '/api/v1/admin/tickets/{id}/escalate',
        tags: ['Phase14-Support'],
        summary: 'Escalate ticket',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['escalate_to', 'reason'],
                properties: [
                    new OA\Property(property: 'escalate_to', type: 'integer', example: 1),
                    new OA\Property(property: 'reason', type: 'string', example: 'SLA breached — needs senior review'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Escalated')]
    )]
    public function ticketsEscalate(): void {}

    #[OA\Post(
        path: '/api/v1/admin/tickets/{id}/resolve',
        tags: ['Phase14-Support'],
        summary: 'Resolve ticket',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['resolution_category', 'resolution_note'],
                properties: [
                    new OA\Property(property: 'resolution_category', type: 'string', example: 'settlement'),
                    new OA\Property(property: 'resolution_note', type: 'string', example: 'Settlement batch reconciled and merchant notified.'),
                    new OA\Property(property: 'trigger_csat', type: 'boolean', example: true),
                    new OA\Property(property: 'csat_score', type: 'integer', minimum: 1, maximum: 5),
                    new OA\Property(property: 'csat_comment', type: 'string'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Resolved')]
    )]
    public function ticketsResolve(): void {}
}
