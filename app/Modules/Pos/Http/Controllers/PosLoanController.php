<?php

namespace App\Modules\Pos\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LoanApplication;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Support\Str;

class PosLoanController extends PosBaseController
{
    /**
     * View history of loans originated at this store.
     */
    public function index(Request $request)
    {
        $storeId = $this->getStoreId($request);
        
        $query = LoanApplication::where('store_id', $storeId)->with(['customer', 'product']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $loans = $query->orderBy('created_at', 'desc')
            ->paginate($request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $loans->items(),
            'meta'    => [
                'current_page' => $loans->currentPage(),
                'last_page'    => $loans->lastPage(),
                'total'        => $loans->total()
            ]
        ]);
    }

    /**
     * Show loan details.
     */
    public function show(Request $request, $id)
    {
        $storeId = $this->getStoreId($request);
        
        $loan = LoanApplication::where('store_id', $storeId)
            ->with(['customer', 'product'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $loan
        ]);
    }

    /**
     * Step 1: Initiate (Customer Details)
     */
    public function initiate(Request $request)
    {
        $validated = $request->validate([
            'mobile' => 'required|string|size:10',
            'pan'    => 'required|string|size:10',
            'name'   => 'required|string',
        ]);

        // Find or create customer
        $customer = Customer::firstOrCreate(
            ['mobile' => $validated['mobile']],
            [
                'name' => $validated['name'],
                'pan_number' => $validated['pan'],
                'email' => $request->input('email'),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Customer verified successfully.',
            'data'    => [
                'customer_id' => $customer->id,
                'name'        => $customer->name,
                'mobile'      => $customer->mobile,
            ]
        ]);
    }

    /**
     * Step 3: Calculate Offers based on Product & Amount
     * In a real system, this queries the pricing engine.
     */
    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'amount'     => 'required|numeric|min:1000',
        ]);

        // Mocking available EMI options for POS selection
        $offers = [
            [
                'id' => 1,
                'lender' => 'HDFC Bank',
                'tenure' => 6,
                'roi' => '0%', // No Cost EMI
                'emi_amount' => round($validated['amount'] / 6, 2),
                'downpayment' => 0,
            ],
            [
                'id' => 2,
                'lender' => 'Bajaj Finserv',
                'tenure' => 9,
                'roi' => '12%',
                'emi_amount' => round(($validated['amount'] * 1.12) / 9, 2),
                'downpayment' => round($validated['amount'] * 0.10, 2), // 10% DP
            ]
        ];

        return response()->json([
            'success' => true,
            'data'    => $offers
        ]);
    }

    /**
     * Step 4: Submit Loan Application
     */
    public function submit(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'product_id'  => 'required|exists:products,id',
            'amount'      => 'required|numeric',
            'tenure'      => 'required|integer',
            'lender'      => 'required|string'
        ]);

        $storeId = $this->getStoreId($request);
        $merchantId = $this->getMerchantId($request);

        // Find lender by name (mocked for phase 5)
        $lenderModel = \App\Models\Lender::where('name', 'like', "%{$validated['lender']}%")->first();
        $lenderId = $lenderModel ? $lenderModel->id : 1; // fallback

        // Create the loan application
        $loan = LoanApplication::create([
            'customer_id'        => $validated['customer_id'],
            'merchant_id'        => $merchantId,
            'store_id'           => $storeId,
            'product_id'         => $validated['product_id'],
            'lender_id'          => $lenderId,
            'amount'             => $validated['amount'],
            'tenure_months'      => $validated['tenure'],
            'status'             => 'approved', // Auto-approved for POS flow testing
            'application_payload'=> [
                'application_number' => 'APP-' . strtoupper(Str::random(8)),
                'approved_amount'    => $validated['amount'],
                'interest_rate'      => 12.0,
                'applied_at'         => now()->toIso8601String(),
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Loan application submitted and approved.',
            'data'    => [
                'loan_id' => $loan->id,
                'application_number' => $loan->application_payload['application_number'] ?? ('APP-' . str_pad($loan->id, 5, '0', STR_PAD_LEFT)),
                'status' => $loan->status,
                'esign_url' => url("/esign/dummy?app_id={$loan->id}"),
            ]
        ]);
    }
}
