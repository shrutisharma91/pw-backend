<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Requests\Profile\ConfirmMfaSetupRequest;
use App\Http\Requests\Profile\ConfirmPasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use App\Services\MFAService;
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
    public function __construct(
        private ProfileService $profileService,
        private MFAService $mfaService,
    ) {}

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

    #[OA\Post(
        path: '/api/v1/admin/profile',
        summary: 'Update Profile Details (multipart / photo upload)',
        description: 'Use this POST variant for multipart/form-data uploads (e.g. profile photo). PHP cannot parse multipart bodies on PUT, so file uploads must be sent via POST. Updates name, email, mobile, theme, timezone, notification preferences, and optional profile photo.',
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
    #[OA\Put(
        path: '/api/v1/admin/profile',
        summary: 'Update Profile Details (JSON, no file)',
        description: 'Updates name, email, mobile, theme, timezone, and notification preferences. For profile photo uploads use the POST variant (multipart/form-data is not parsed on PUT).',
        tags: ['2. Personal Profile'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
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
                ]
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

    #[OA\Get(
        path: '/api/v1/admin/profile/mfa',
        summary: 'Get MFA configuration status',
        description: 'Returns the current MFA channel, whether an authenticator app is configured, and how many recovery codes remain.',
        tags: ['2. Personal Profile'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'MFA status fetched successfully.')]
    )]
    public function mfaStatus(): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data'    => [
                'mfa_enabled'              => (bool) $user->mfa_enabled,
                'mfa_channel'              => $user->mfa_channel ?? 'email',
                'totp_configured'          => ! empty($user->mfa_secret),
                'recovery_codes_remaining' => $this->mfaService->recoveryCodesRemaining($user),
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/v1/admin/profile/mfa/setup',
        summary: 'Begin authenticator (TOTP) reconfiguration',
        description: 'Re-authenticates with the current password and returns a fresh TOTP secret plus an otpauth URI for QR rendering. The secret is not active until confirmed via /profile/mfa/confirm.',
        tags: ['2. Personal Profile'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', format: 'password', example: 'Current@password123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Setup initiated; scan the QR and confirm.'),
            new OA\Response(response: 422, description: 'Validation error.'),
        ]
    )]
    public function mfaSetup(ConfirmPasswordRequest $request): JsonResponse
    {
        $data = $this->mfaService->beginTotpSetup($request->user());

        activity()->log('MFA TOTP reconfiguration initiated by admin#' . $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Scan the QR code or enter the secret in your authenticator app, then confirm with a generated code.',
            'data'    => $data,
        ]);
    }

    #[OA\Post(
        path: '/api/v1/admin/profile/mfa/confirm',
        summary: 'Confirm authenticator (TOTP) reconfiguration',
        description: 'Verifies a code from the authenticator app, activates TOTP as the MFA channel, and returns a fresh set of recovery codes (shown only once).',
        tags: ['2. Personal Profile'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', example: '123456'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Authenticator configured; recovery codes returned.'),
            new OA\Response(response: 422, description: 'Invalid or expired code.'),
        ]
    )]
    public function mfaConfirm(ConfirmMfaSetupRequest $request): JsonResponse
    {
        $result = $this->mfaService->confirmTotpSetup($request->user(), $request->input('code'));

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'code'    => $result['code'] ?? null,
            ], 422);
        }

        activity()->log('MFA reconfigured to authenticator app (TOTP) by admin#' . $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Authenticator app configured successfully. Store these recovery codes securely — they will not be shown again.',
            'data'    => [
                'mfa_channel'    => 'totp',
                'recovery_codes' => $result['recovery_codes'],
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/v1/admin/profile/mfa/use-email',
        summary: 'Switch MFA channel to email OTP',
        description: 'Reverts the MFA channel to email OTP and removes any configured authenticator secret. Requires the current password.',
        tags: ['2. Personal Profile'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', format: 'password', example: 'Current@password123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'MFA channel switched to email.'),
            new OA\Response(response: 422, description: 'Validation error.'),
        ]
    )]
    public function mfaUseEmail(ConfirmPasswordRequest $request): JsonResponse
    {
        $this->mfaService->switchToEmailChannel($request->user());

        activity()->log('MFA channel switched to email OTP by admin#' . $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'MFA channel switched to email OTP.',
            'data'    => ['mfa_channel' => 'email'],
        ]);
    }

    #[OA\Post(
        path: '/api/v1/admin/profile/recovery-codes/regenerate',
        summary: 'Regenerate MFA recovery codes',
        description: 'Issues a fresh set of single-use recovery codes (shown only once) and invalidates all previous codes. Requires the current password.',
        tags: ['2. Personal Profile'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', format: 'password', example: 'Current@password123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'New recovery codes generated.'),
            new OA\Response(response: 422, description: 'Validation error.'),
        ]
    )]
    public function regenerateRecoveryCodes(ConfirmPasswordRequest $request): JsonResponse
    {
        $codes = $this->mfaService->regenerateRecoveryCodes($request->user());

        activity()->log('MFA recovery codes regenerated by admin#' . $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'New recovery codes generated. Previous codes are now invalid. Store these securely — they will not be shown again.',
            'data'    => [
                'recovery_codes' => $codes,
                'count'          => count($codes),
            ],
        ]);
    }
}
