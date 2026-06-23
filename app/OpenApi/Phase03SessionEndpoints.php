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
}
