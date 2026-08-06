<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

/**
 * Phase 2 Screen 13 — Customer Login & Mobile OTP
 */
class AuthController extends Controller
{
    private const DEV_OTP = '123456';
    private const OTP_TTL_SECONDS = 300;

    #[OA\Post(
        path: '/api/v1/customer/auth/send-otp',
        summary: 'Request customer mobile OTP',
        tags: ['Phase2-Customer'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['phone'],
                properties: [
                    new OA\Property(property: 'phone', type: 'string', example: '9876543210'),
                    new OA\Property(property: 'whatsapp_opt_in', type: 'boolean', example: true),
                    new OA\Property(property: 'privacy_accepted', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OTP sent'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function sendOtp(Request $request)
    {
        $data = $request->validate([
            'phone'            => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'whatsapp_opt_in'  => ['sometimes', 'boolean'],
            'privacy_accepted' => ['sometimes', 'boolean'],
        ]);

        $phone = $data['phone'];
        $otp = self::DEV_OTP;

        CustomerOtp::where('phone', $phone)->whereNull('verified_at')->delete();

        CustomerOtp::create([
            'phone'      => $phone,
            'otp_hash'   => Hash::make($otp),
            'expires_at' => now()->addSeconds(self::OTP_TTL_SECONDS),
            'attempts'   => 0,
        ]);

        Customer::firstOrCreate(
            ['phone' => $phone],
            [
                'name'            => 'Customer ' . substr($phone, -4),
                'whatsapp_opt_in' => (bool) ($data['whatsapp_opt_in'] ?? false),
                'is_active'       => true,
            ]
        );

        if (isset($data['whatsapp_opt_in'])) {
            Customer::where('phone', $phone)->update([
                'whatsapp_opt_in' => (bool) $data['whatsapp_opt_in'],
            ]);
        }

        $payload = [
            'success'      => true,
            'message'      => 'OTP sent successfully.',
            'phone'        => $phone,
            'resend_after' => 30,
            'expires_in'   => self::OTP_TTL_SECONDS,
        ];

        // Local/testing only — never expose OTP in production
        if (app()->environment('local', 'testing')) {
            $payload['dev_otp'] = $otp;
        }

        return response()->json($payload);
    }

    #[OA\Post(
        path: '/api/v1/customer/auth/verify-otp',
        summary: 'Verify customer mobile OTP and issue JWT',
        tags: ['Phase2-Customer'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['phone', 'otp'],
                properties: [
                    new OA\Property(property: 'phone', type: 'string', example: '9876543210'),
                    new OA\Property(property: 'otp', type: 'string', example: '123456'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'JWT issued'),
            new OA\Response(response: 401, description: 'Invalid OTP'),
        ]
    )]
    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'otp'   => ['required', 'string', 'size:6'],
        ]);

        $record = CustomerOtp::where('phone', $data['phone'])
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (!$record || $record->expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'OTP expired or not found. Please request a new one.',
            ], 401);
        }

        if ($record->attempts >= 5) {
            return response()->json([
                'success' => false,
                'message' => 'Too many invalid attempts. Please request a new OTP.',
            ], 429);
        }

        if (!Hash::check($data['otp'], $record->otp_hash)) {
            $record->increment('attempts');
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP.',
            ], 401);
        }

        $record->update(['verified_at' => now()]);

        $customer = Customer::where('phone', $data['phone'])->firstOrFail();
        if (!$customer->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Customer account is disabled.',
            ], 403);
        }

        $customer->update(['last_login_at' => now()]);

        $token = auth('customer')->login($customer);

        return response()->json([
            'success'      => true,
            'message'      => 'Login successful.',
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => auth('customer')->factory()->getTTL() * 60,
            'customer'     => [
                'id'              => $customer->id,
                'name'            => $customer->name,
                'phone'           => $customer->phone,
                'email'           => $customer->email,
                'whatsapp_opt_in' => $customer->whatsapp_opt_in,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/v1/customer/auth/logout',
        summary: 'Invalidate customer JWT',
        tags: ['Phase2-Customer'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Logged out'),
        ]
    )]
    public function logout()
    {
        auth('customer')->logout();

        return response()->json([
            'success' => true,
            'message' => 'Logged out.',
        ]);
    }
}
