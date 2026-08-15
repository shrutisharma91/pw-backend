<?php

namespace App\Modules\Agent\Http\Controllers;

use App\Models\Merchant;
use App\Models\Store;
use App\Services\GstinLookupService;
use Illuminate\Http\Request;

/**
 * Phase 6 Screen 44 — On-Ground Merchant Onboarding
 */
class OnboardingController extends AgentBaseController
{
    public function __construct(private GstinLookupService $gstinLookup)
    {
    }

    public function verifyGstin(Request $request)
    {
        $data = $request->validate([
            'gstin' => ['required', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i'],
        ]);

        $gstin = strtoupper($data['gstin']);
        $lookup = $this->gstinLookup->lookup($gstin);

        $existing = Merchant::query()->where('gst_number', $gstin)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'gstin' => $lookup['gstin'],
                'legal_name' => $lookup['legal_name'],
                'trade_name' => $lookup['trade_name'],
                'address' => $lookup['address'],
                'state' => $lookup['state'],
                'pincode' => $lookup['pincode'],
                'gst_status' => $lookup['status'],
                'already_onboarded' => (bool) $existing,
                'existing_merchant_id' => $existing?->id,
                'owned_by_this_agent' => $existing?->sales_exec_id === $this->scopedSalesExecId(),
            ],
        ]);
    }

    public function index(Request $request)
    {
        $query = $this->agentMerchants()->withCount('stores')->latest();

        if ($stage = $request->query('stage')) {
            $query->where('onboarding_stage', $stage);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $rows = $query->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    public function show(int $id)
    {
        $merchant = $this->findAgentMerchant($id);
        $merchant->load('stores');

        return response()->json([
            'success' => true,
            'data' => $merchant,
        ]);
    }

    public function onboard(Request $request)
    {
        $data = $request->validate([
            'gstin' => ['required', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'pan_number' => ['nullable', 'string', 'size:10'],
            'category' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:10'],
            'address' => ['nullable', 'string'],
            'store_facade_url' => ['nullable', 'string', 'max:2048'],
            'store_counter_url' => ['nullable', 'string', 'max:2048'],
            'cheque_url' => ['nullable', 'string', 'max:2048'],
            'bank_account_number' => ['nullable', 'string', 'max:30'],
            'bank_ifsc' => ['nullable', 'string', 'max:20'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'initiate_esign' => ['sometimes', 'boolean'],
            'store' => ['nullable', 'array'],
            'store.name' => ['required_with:store', 'string', 'max:255'],
            'store.address' => ['nullable', 'string'],
            'store.latitude' => ['nullable', 'numeric'],
            'store.longitude' => ['nullable', 'numeric'],
        ]);

        $gstin = strtoupper($data['gstin']);
        $existing = Merchant::query()->where('gst_number', $gstin)->first();

        if ($existing && (int) $existing->sales_exec_id !== $this->scopedSalesExecId()) {
            return response()->json([
                'success' => false,
                'message' => 'This GSTIN is already onboarded by another agent.',
                'code' => 'gstin_owned_by_other_agent',
            ], 409);
        }

        $lookup = $this->gstinLookup->lookup($gstin);
        $agentId = $this->scopedSalesExecId();

        $payload = [
            'business_name' => $data['business_name'] ?? $lookup['trade_name'],
            'gst_number' => $gstin,
            'gst_legal_name' => $lookup['legal_name'],
            'gst_address' => $lookup['address'],
            'gst_verified_at' => now(),
            'pan_number' => strtoupper($data['pan_number'] ?? substr($gstin, 2, 10)),
            'category' => $data['category'] ?? 'Electronics',
            'region' => $data['region'] ?? $lookup['state'],
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? $lookup['state'],
            'pincode' => $data['pincode'] ?? $lookup['pincode'],
            'address' => $data['address'] ?? $lookup['address'],
            'sales_exec_id' => $agentId,
            'store_facade_url' => $data['store_facade_url'] ?? null,
            'store_counter_url' => $data['store_counter_url'] ?? null,
            'cheque_url' => $data['cheque_url'] ?? null,
            'bank_account_number' => $data['bank_account_number'] ?? null,
            'bank_ifsc' => isset($data['bank_ifsc']) ? strtoupper($data['bank_ifsc']) : null,
            'bank_account_name' => $data['bank_account_name'] ?? null,
        ];

        $merchant = $existing ?: new Merchant();
        $merchant->fill($payload);

        if ($request->boolean('initiate_esign')) {
            $merchant->agreement_esign_status = 'initiated';
            $merchant->agreement_esign_at = now();
        }

        $this->applyOnboardingStage($merchant);
        $merchant->save();

        $store = null;
        if (!empty($data['store']['name'])) {
            $store = Store::query()->firstOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'name' => $data['store']['name'],
                ],
                [
                    'address' => $data['store']['address'] ?? $merchant->address,
                    'latitude' => $data['store']['latitude'] ?? null,
                    'longitude' => $data['store']['longitude'] ?? null,
                    'status' => 'active',
                ]
            );
            $merchant->store_count = $merchant->stores()->count();
            $merchant->save();
        }

        return response()->json([
            'success' => true,
            'message' => $existing ? 'Merchant onboarding file updated.' : 'Merchant onboarding file submitted.',
            'data' => [
                'merchant' => $merchant->fresh('stores'),
                'store' => $store,
            ],
        ], $existing ? 200 : 201);
    }

    public function update(Request $request, int $id)
    {
        $merchant = $this->findAgentMerchant($id);

        $data = $request->validate([
            'business_name' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'string', 'max:100'],
            'store_facade_url' => ['nullable', 'string', 'max:2048'],
            'store_counter_url' => ['nullable', 'string', 'max:2048'],
            'cheque_url' => ['nullable', 'string', 'max:2048'],
            'bank_account_number' => ['nullable', 'string', 'max:30'],
            'bank_ifsc' => ['nullable', 'string', 'max:20'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
        ]);

        if (isset($data['bank_ifsc'])) {
            $data['bank_ifsc'] = strtoupper($data['bank_ifsc']);
        }

        $merchant->fill($data);
        $this->applyOnboardingStage($merchant);
        $merchant->save();

        return response()->json([
            'success' => true,
            'data' => $merchant->fresh(),
        ]);
    }

    public function initiateEsign(int $id)
    {
        $merchant = $this->findAgentMerchant($id);
        $merchant->agreement_esign_status = 'initiated';
        $merchant->agreement_esign_at = now();
        $this->applyOnboardingStage($merchant);
        $merchant->save();

        return response()->json([
            'success' => true,
            'message' => 'Digital subvention agreement eSign initiated.',
            'data' => [
                'merchant_id' => $merchant->id,
                'agreement_esign_status' => $merchant->agreement_esign_status,
                'esign_url' => url("/esign/merchant-agreement?merchant_id={$merchant->id}"),
            ],
        ]);
    }

    private function applyOnboardingStage(Merchant $merchant): void
    {
        $hasPhotos = filled($merchant->store_facade_url) || filled($merchant->store_counter_url);
        $hasBank = filled($merchant->bank_account_number) && filled($merchant->bank_ifsc);
        $hasEsign = in_array($merchant->agreement_esign_status, ['initiated', 'signed'], true);

        if ($merchant->status === 'Approved' || $merchant->onboarding_stage === 'approved') {
            $merchant->onboarding_stage = 'approved';
            $merchant->status = 'Approved';
            return;
        }

        if ($merchant->onboarding_stage === 'inspection_done' || $merchant->status === 'Under Review') {
            $merchant->onboarding_stage = 'inspection_done';
            $merchant->status = 'Under Review';
            return;
        }

        if ($hasPhotos && $hasBank && $hasEsign) {
            $merchant->onboarding_stage = 'docs_collected';
            $merchant->status = 'Submitted';
            return;
        }

        if ($hasPhotos || $hasBank) {
            $merchant->onboarding_stage = 'docs_collected';
            $merchant->status = 'Submitted';
            return;
        }

        $merchant->onboarding_stage = 'lead';
        $merchant->status = $merchant->status ?: 'Draft';
        if (!in_array($merchant->status, ['Draft', 'Submitted', 'Under Review', 'Approved', 'Rejected'], true)) {
            $merchant->status = 'Draft';
        }
        if ($merchant->onboarding_stage === 'lead' && !in_array($merchant->status, ['Submitted', 'Under Review', 'Approved'], true)) {
            $merchant->status = 'Draft';
        }
    }
}
