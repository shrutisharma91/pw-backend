<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class Phase03UserEndpoints
{
    #[OA\Post(
        path: '/api/v1/admin/users',
        tags: ['7. User Management'],
        summary: 'Create Portal User',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'mobile', 'role'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'mobile', type: 'string', example: '9876543210'),
                    new OA\Property(property: 'role', type: 'string', example: 'store_manager'),
                    new OA\Property(property: 'merchant_id', type: 'integer', nullable: true, example: 1),
                    new OA\Property(property: 'merchant_scope', type: 'string', enum: ['platform', 'merchant', 'store'], nullable: true, example: 'merchant'),
                    new OA\Property(property: 'assigned_store_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2]),
                    new OA\Property(property: 'force_mfa', type: 'boolean', example: true),
                    new OA\Property(property: 'password_expiry_policy', type: 'string', enum: ['default', 'never', '30_days', '60_days', '90_days', '180_days'], example: 'default'),
                    new OA\Property(property: 'activation_date', type: 'string', format: 'date', nullable: true, example: '2026-06-22'),
                    new OA\Property(property: 'deactivation_date', type: 'string', format: 'date', nullable: true, example: '2026-12-31'),
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: 'User created successfully.')]
    )]
    public function createUser(): void {}

    #[OA\Get(
        path: '/api/v1/admin/users/{id}',
        tags: ['7. User Management'],
        summary: 'Get User Details',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'User details fetched.')]
    )]
    public function showUser(): void {}

    #[OA\Put(
        path: '/api/v1/admin/users/{id}',
        tags: ['7. User Management'],
        summary: 'Update User Info',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Alice Smith'),
                    new OA\Property(property: 'mobile', type: 'string', example: '9876543210'),
                    new OA\Property(property: 'role', type: 'string', example: 'store_manager'),
                    new OA\Property(property: 'merchant_id', type: 'integer', nullable: true, example: 1),
                    new OA\Property(property: 'merchant_scope', type: 'string', enum: ['platform', 'merchant', 'store'], nullable: true),
                    new OA\Property(property: 'assigned_store_ids', type: 'array', items: new OA\Items(type: 'integer')),
                    new OA\Property(property: 'force_mfa', type: 'boolean'),
                    new OA\Property(property: 'password_expiry_policy', type: 'string', enum: ['default', 'never', '30_days', '60_days', '90_days', '180_days']),
                    new OA\Property(property: 'activation_date', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'deactivation_date', type: 'string', format: 'date', nullable: true),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'User updated.')]
    )]
    public function updateUser(): void {}
}
