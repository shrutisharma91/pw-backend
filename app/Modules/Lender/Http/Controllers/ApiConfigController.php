<?php

namespace App\Modules\Lender\Http\Controllers;

use App\Models\Lender;
use Illuminate\Http\Request;

class ApiConfigController extends LenderBaseController
{
    public function show()
    {
        $lender = Lender::query()->where('id', $this->scopedLenderId())->firstOrFail();
        $credentials = $lender->api_credentials ?? [];

        return response()->json([
            'success' => true,
            'data' => [
                'lender_id' => $lender->id,
                'name' => $lender->name,
                'api_base_url' => $lender->api_base_url,
                'webhook_url' => $credentials['webhook_url'] ?? ($lender->webhook_endpoints[0] ?? null),
                'product_categories' => $credentials['product_categories'] ?? [],
                'min_loan_amount' => $lender->min_loan_amount,
                'max_loan_amount' => $lender->max_loan_amount,
                'api_key_masked' => isset($credentials['api_key']) ? '****' . substr((string) $credentials['api_key'], -4) : null,
                'api_status' => $lender->api_status,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'api_base_url' => 'sometimes|nullable|url',
            'webhook_url' => 'sometimes|nullable|url',
            'product_categories' => 'sometimes|array',
            'min_loan_amount' => 'sometimes|nullable|numeric',
            'max_loan_amount' => 'sometimes|nullable|numeric',
            // Empty inputs become null via ConvertEmptyStringsToNull — must allow nullable.
            'api_key' => 'sometimes|nullable|string',
            'api_secret' => 'sometimes|nullable|string',
        ]);

        $lender = Lender::query()->where('id', $this->scopedLenderId())->firstOrFail();
        $credentials = $lender->api_credentials ?? [];

        if ($request->filled('webhook_url')) {
            $credentials['webhook_url'] = $request->webhook_url;
        } elseif ($request->exists('webhook_url') && $request->input('webhook_url') === null) {
            // leave existing webhook when blank field submitted
        }

        if ($request->filled('product_categories')) {
            $credentials['product_categories'] = $request->product_categories;
        }
        // Only overwrite secrets when the user actually typed a new value.
        if ($request->filled('api_key')) {
            $credentials['api_key'] = $request->api_key;
        }
        if ($request->filled('api_secret')) {
            $credentials['api_secret'] = $request->api_secret;
        }

        $lender->fill($request->only(['api_base_url', 'min_loan_amount', 'max_loan_amount']));
        $lender->api_credentials = $credentials;
        $lender->save();

        return $this->show();
    }

    public function testConnection()
    {
        $lender = Lender::query()->where('id', $this->scopedLenderId())->firstOrFail();

        return response()->json([
            'success' => true,
            'message' => 'Connection test succeeded (stub).',
            'data' => [
                'lender_id' => $lender->id,
                'latency_ms' => 142,
                'http_status' => 200,
            ],
        ]);
    }
}
