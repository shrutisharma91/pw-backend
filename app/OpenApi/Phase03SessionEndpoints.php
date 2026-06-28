<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class Phase03SessionEndpoints
{
    #[OA\Post(
        path: '/api/v1/admin/sessions/bulk-revoke',
        tags: ['10. Device & Sessions'],
        summary: 'Bulk revoke sessions',
        description: 'Revoke multiple active sessions in a single request. Already revoked or missing sessions are counted in failed_count.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['session_ids'],
                properties: [
                    new OA\Property(
                        property: 'session_ids',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2, 3]
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Bulk revoke summary',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'revoked_count', type: 'integer', example: 3),
                        new OA\Property(property: 'failed_count', type: 'integer', example: 0),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function bulkRevokeSessions(): void {}

    #[OA\Post(
        path: '/api/v1/admin/sessions/revoke-all-suspicious',
        tags: ['10. Device & Sessions'],
        summary: 'Revoke all suspicious sessions',
        description: 'Force-logout every active session currently flagged as suspicious, across all users platform-wide. Returns how many sessions were revoked.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Suspicious sessions revoked',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: '4 suspicious session(s) revoked across the platform.'),
                        new OA\Property(property: 'sessions_revoked', type: 'integer', example: 4),
                    ]
                )
            ),
        ]
    )]
    public function revokeAllSuspicious(): void {}
}
