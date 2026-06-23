<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/*
|--------------------------------------------------------------------------
| ProfileController
|--------------------------------------------------------------------------
| Handles Screen 04 — Profile & Personal Settings
|
| APIs:
|   GET  /api/v1/admin/profile                    → get my profile
|   PUT  /api/v1/admin/profile                    → update profile settings
|   POST /api/v1/admin/profile/change-password    → self password change
*/

class ProfileController extends Controller
{
    public function __construct(private ProfileService $profileService) {}

    #[OA\Get(
        path: '/api/v1/admin/profile',
        summary: 'Get Profile Info',
        tags: ['2. Personal Profile'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Profile data fetched successfully.')]
    )]
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new ProfileResource(auth()->user()),
        ]);
    }

    #[OA\Put(
        path: '/api/v1/admin/profile',
        summary: 'Update Profile Details',
        description: 'Updates name, email, mobile, theme, timezone, notification preferences, and optional profile photo.',
        tags: ['2. Personal Profile'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'name', type: 'string', example: 'Updated Name'),
                        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@finz.com'),
                        new OA\Property(property: 'mobile', type: 'string', example: '9876543210'),
                        new OA\Property(property: 'theme', type: 'string', enum: ['light', 'dark'], example: 'dark'),
                        new OA\Property(property: 'timezone', type: 'string', example: 'Asia/Kolkata'),
                        new OA\Property(
                            property: 'notification_preferences',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'email', type: 'boolean', example: true),
                                new OA\Property(property: 'sms', type: 'boolean', example: false),
                                new OA\Property(property: 'whatsapp', type: 'boolean', example: false),
                                new OA\Property(property: 'in_app', type: 'boolean', example: true),
                            ]
                        ),
                        new OA\Property(property: 'profile_photo', type: 'string', format: 'binary'),
                    ]
                )
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Profile updated successfully.')]
    )]
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->profileService->updateProfile(
            $request->user(),
            $request->validated(),
            $request->file('profile_photo')
        );

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data'    => new ProfileResource($user),
        ]);
    }

    #[OA\Post(
        path: '/api/v1/admin/profile/change-password',
        summary: 'Change own password',
        tags: ['2. Personal Profile'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'new_password', 'new_password_confirmation'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', format: 'password', example: 'Old@password123'),
                    new OA\Property(property: 'new_password', type: 'string', format: 'password', example: 'New@password123'),
                    new OA\Property(property: 'new_password_confirmation', type: 'string', format: 'password', example: 'New@password123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password changed successfully.'),
            new OA\Response(response: 422, description: 'Validation error.'),
        ]
    )]
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->profileService->changePassword(
            $request->user(),
            $request->input('new_password'),
            $this->profileService->currentTokenId()
        );

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully. Other active sessions have been logged out.',
        ]);
    }
}
