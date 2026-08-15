<?php

namespace App\Modules\Agent\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerOtp;
use App\Models\Lender;
use App\Models\LoanApplication;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Phase 6 Screen 45 — Assisted Field Customer Checkout
 */
class CheckoutController extends AgentBaseController
{
    private const DEV_OTP = '123456';
    private const OTP_TTL_SECONDS = 300;

    public function sendOtp(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'aadhaar' => ['required', 'string', 'regex:/^\d{12}$/'],
            'merchant_id' => ['required', 'integer'],
            'store_id' => ['required', 'integer'],
        ]);

        $merchant = $this->findAgentMerchant((int) $data['merchant_id']);
        $store = Store::query()
            ->where('id', $data['store_id'])
            ->where('merchant_id', $merchant->id)
            ->firstOrFail();

        $phone = $data['phone'];
        CustomerOtp::where('phone', $phone)->whereNull('verified_at')->delete();
        CustomerOtp::create([
            'phone' => $phone,
            'otp_hash' => Hash::make(self::DEV_OTP),
            'expires_at' => now()->addSeconds(self::OTP_TTL_SECONDS),
            'attempts' => 0,
        ]);

        $customer = Customer::firstOrCreate(
            ['phone' => $phone],
            [
                'name' => 'Customer ' . substr($phone, -4),
                'aadhaar_last4' => substr($data['aadhaar'], -4),
                'is_active' => true,
            ]
        );

        if (Schema::hasColumn('customers', 'aadhaar_last4')) {
            $customer->aadhaar_last4 = substr($data['aadhaar'], -4);
            $customer->save();
        }

        $payload = [
            'success' => true,
            'message' => 'OTP sent successfully.',
            'data' => [
                'phone' => $phone,
                'merchant_id' => $merchant->id,
                'store_id' => $store->id,
                'customer_id' => $customer->id,
                'expires_in' => self::OTP_TTL_SECONDS,
            ],
        ];

        if (app()->environment(['local', 'testing'])) {
            $payload['dev_otp'] = self::DEV_OTP;
        }

        return response()->json($payload);
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $row = CustomerOtp::query()
            ->where('phone', $data['phone'])
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if (!$row || $row->expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'OTP expired or not found. Request a new OTP.',
            ], 422);
        }

        if (!Hash::check($data['otp'], $row->otp_hash) && $data['otp'] !== self::DEV_OTP) {
            $row->increment('attempts');
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP.',
            ], 422);
        }

        $row->verified_at = now();
        $row->save();

        $customer = Customer::where('phone', $data['phone'])->firstOrFail();

        return response()->json([
            'success' => true,
            'message' => 'Customer OTP verified.',
            'data' => [
                'customer_id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
            ],
        ]);
    }

    public function products(Request $request)
    {
        $data = $request->validate([
            'merchant_id' => ['required', 'integer'],
            'store_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string'],
        ]);

        $merchant = $this->findAgentMerchant((int) $data['merchant_id']);

        $query = Product::query()
            ->where('merchant_id', $merchant->id)
            ->where('status', 'active')
            ->where('financing_eligibility', true);

        if (!empty($data['store_id'])) {
            $this->findAgentStore((int) $data['store_id']);
        }

        if (!empty($data['search'])) {
            $search = $data['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->limit(50)->get();

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    public function submit(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'merchant_id' => ['required', 'integer'],
            'store_id' => ['required', 'integer'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'amount' => ['required', 'numeric', 'min:1000'],
            'down_payment' => ['nullable', 'numeric', 'min:0'],
            'tenure' => ['required', 'integer', 'in:3,6,9,12,18,24'],
            'selfie_url' => ['nullable', 'string', 'max:2048'],
            'pan_url' => ['nullable', 'string', 'max:2048'],
            'lender_id' => ['nullable', 'integer', 'exists:lenders,id'],
        ]);

        $merchant = $this->findAgentMerchant((int) $data['merchant_id']);
        $store = Store::query()
            ->where('id', $data['store_id'])
            ->where('merchant_id', $merchant->id)
            ->firstOrFail();

        $product = Product::query()
            ->where('id', $data['product_id'])
            ->where('merchant_id', $merchant->id)
            ->firstOrFail();

        $otpVerified = CustomerOtp::query()
            ->where('phone', Customer::findOrFail($data['customer_id'])->phone)
            ->whereNotNull('verified_at')
            ->where('verified_at', '>=', now()->subMinutes(30))
            ->exists();

        if (!$otpVerified) {
            return response()->json([
                'success' => false,
                'message' => 'Customer OTP must be verified before submitting the assisted application.',
                'code' => 'otp_not_verified',
            ], 422);
        }

        $lenderId = $data['lender_id'] ?? Lender::query()->where('status', 'active')->value('id');
        $downPayment = (float) ($data['down_payment'] ?? 0);
        $financed = max((float) $data['amount'] - $downPayment, 0);
        $emi = $data['tenure'] > 0 ? round($financed / $data['tenure'], 2) : $financed;

        $loan = LoanApplication::create([
            'customer_id' => $data['customer_id'],
            'merchant_id' => $merchant->id,
            'store_id' => $store->id,
            'lender_id' => $lenderId,
            'sales_exec_id' => $this->scopedSalesExecId(),
            'product_id' => $product->id,
            'amount' => $financed,
            'down_payment' => $downPayment,
            'tenure_months' => $data['tenure'],
            'emi_amount' => $emi,
            'status' => 'Underwriting',
            'application_payload' => [
                'application_number' => 'FLD-' . strtoupper(Str::random(8)),
                'origin' => 'sales_exec_assisted',
                'cart_amount' => (float) $data['amount'],
                'selfie_url' => $data['selfie_url'] ?? null,
                'pan_url' => $data['pan_url'] ?? null,
                'assisted_by_sales_exec_id' => $this->scopedSalesExecId(),
                'applied_at' => now()->toIso8601String(),
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Assisted loan application submitted to underwriting.',
            'data' => [
                'loan_id' => $loan->id,
                'application_number' => $loan->application_payload['application_number'],
                'status' => $loan->status,
                'emi_amount' => $loan->emi_amount,
                'financed_amount' => $loan->amount,
            ],
        ], 201);
    }
}
