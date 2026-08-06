<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Lender;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanInstallment;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

/**
 * Phase 2 Screens 15–18 — Loan wizard, dashboard, schedule/SOA, foreclosure/NOC
 */
class LoanController extends Controller
{
    #[OA\Post(
        path: '/api/v1/customer/loans/eligibility-check',
        summary: 'Check CIBIL & loan eligibility (stubbed for local testing)',
        tags: ['Phase2-Customer'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'full_name', type: 'string', example: 'Rohan Sharma'),
                    new OA\Property(property: 'pan', type: 'string', example: 'ABCDE1234F'),
                    new OA\Property(property: 'dob', type: 'string', format: 'date', example: '1995-08-15'),
                    new OA\Property(property: 'monthly_income', type: 'number', example: 75000),
                    new OA\Property(property: 'loan_amount', type: 'number', example: 134900),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Eligibility result')]
    )]
    public function eligibilityCheck(Request $request)
    {
        $data = $request->validate([
            'full_name'      => ['required', 'string', 'max:120'],
            'pan'            => ['required', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'dob'            => ['required', 'date'],
            'monthly_income' => ['required', 'numeric', 'min:10000'],
            'loan_amount'    => ['required', 'numeric', 'min:10000'],
            'aadhaar'        => ['sometimes', 'string'],
        ]);

        /** @var Customer $customer */
        $customer = auth('customer')->user();
        $customer->update([
            'name'           => $data['full_name'],
            'pan_number'     => $data['pan'],
            'dob'            => $data['dob'],
            'monthly_income' => $data['monthly_income'],
            'aadhaar_last4'  => isset($data['aadhaar'])
                ? substr(preg_replace('/\D/', '', $data['aadhaar']), -4)
                : $customer->aadhaar_last4,
        ]);

        $income = (float) $data['monthly_income'];
        $amount = (float) $data['loan_amount'];
        $cibil = $income >= 50000 ? 762 : 680;
        $preApproved = min(500000, max(50000, round($income * 8, -3)));
        $eligible = $cibil >= 650 && $amount <= $preApproved;

        $lenders = Lender::query()
            ->where(function ($q) {
                $q->where('status', 'active')->orWhere('status', 'Active')->orWhereNull('status');
            })
            ->limit(5)
            ->get()
            ->map(function (Lender $l) use ($amount) {
                $emi = (int) round($amount / 6);
                return [
                    'id'           => $l->id,
                    'name'         => $l->name,
                    'loan_amount'  => $amount,
                    'emi'          => $emi,
                    'tenure'       => 6,
                    'fee'          => 0,
                    'badge'        => 'Pre-Approved',
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'eligible'            => $eligible,
                'cibil_score'         => $cibil,
                'pre_approved_limit'  => $preApproved,
                'message'             => $eligible
                    ? 'PAN verified. Pre-approved limit available.'
                    : 'Not eligible for requested amount.',
                'lenders'             => $lenders,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/v1/customer/loans/submit',
        summary: 'Finalize paperless loan application (5-step wizard)',
        tags: ['Phase2-Customer'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'product_id', type: 'integer', example: 1),
                    new OA\Property(property: 'loan_amount', type: 'number', example: 134900),
                    new OA\Property(property: 'down_payment', type: 'number', example: 0),
                    new OA\Property(property: 'tenure_months', type: 'integer', example: 6),
                    new OA\Property(property: 'lender_id', type: 'integer', example: 1),
                    new OA\Property(property: 'selected_lender', type: 'string', example: 'HDFC Bank Ltd'),
                    new OA\Property(property: 'bank_account', type: 'string', example: '502000918237465'),
                    new OA\Property(property: 'ifsc', type: 'string', example: 'HDFC0001234'),
                    new OA\Property(property: 'aadhaar', type: 'string', example: '987654321098'),
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: 'Loan created')]
    )]
    public function submit(Request $request)
    {
        $data = $request->validate([
            'product_id'       => ['sometimes', 'nullable', 'integer', 'exists:products,id'],
            'product_name'     => ['sometimes', 'nullable', 'string', 'max:200'],
            'loan_amount'      => ['required', 'numeric', 'min:10000'],
            'down_payment'     => ['sometimes', 'numeric', 'min:0'],
            'tenure_months'    => ['required', 'integer', 'in:3,6,9,12,18,24'],
            'lender_id'        => ['sometimes', 'nullable', 'integer', 'exists:lenders,id'],
            'selected_lender'  => ['sometimes', 'nullable', 'string'],
            'merchant_id'      => ['sometimes', 'nullable', 'integer', 'exists:merchants,id'],
            'store_id'         => ['sometimes', 'nullable', 'integer', 'exists:stores,id'],
            'bank_account'     => ['required', 'string', 'min:8'],
            'ifsc'             => ['required', 'string', 'min:11', 'max:11'],
            'aadhaar'          => ['sometimes', 'string'],
            'full_name'        => ['sometimes', 'string'],
            'pan'              => ['sometimes', 'string'],
        ]);

        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $product = isset($data['product_id']) ? Product::find($data['product_id']) : null;
        $merchant = isset($data['merchant_id'])
            ? Merchant::find($data['merchant_id'])
            : Merchant::query()->where('status', 'Approved')->first() ?? Merchant::first();
        $store = isset($data['store_id'])
            ? Store::find($data['store_id'])
            : ($merchant ? Store::where('merchant_id', $merchant->id)->first() : null);

        $lender = null;
        if (!empty($data['lender_id'])) {
            $lender = Lender::find($data['lender_id']);
        } elseif (!empty($data['selected_lender'])) {
            $lender = Lender::where('name', 'like', '%' . $data['selected_lender'] . '%')->first();
        }
        $lender = $lender ?? Lender::first();

        $amount = (float) $data['loan_amount'];
        $down = (float) ($data['down_payment'] ?? 0);
        $principal = max(0, $amount - $down);
        $tenure = (int) $data['tenure_months'];
        $emi = round($principal / $tenure, 2);
        $productName = $product?->name ?? ($data['product_name'] ?? 'Consumer Durable');

        $loan = DB::transaction(function () use ($customer, $merchant, $store, $lender, $product, $data, $amount, $down, $principal, $tenure, $emi, $productName) {
            $application = LoanApplication::create([
                'customer_id'         => $customer->id,
                'merchant_id'         => $merchant?->id,
                'store_id'            => $store?->id,
                'lender_id'           => $lender?->id,
                'product_id'          => $product?->id,
                'amount'              => $principal,
                'tenure_months'       => $tenure,
                'down_payment'        => $down,
                'emi_amount'          => $emi,
                'status'              => 'Disbursed',
                'application_payload' => $data,
            ]);

            $accountNo = 'LAN-' . now()->format('Y') . '-' . str_pad((string) (1000 + $application->id), 4, '0', STR_PAD_LEFT);

            $loan = Loan::create([
                'customer_id'         => $customer->id,
                'loan_application_id' => $application->id,
                'account_no'          => $accountNo,
                'merchant_id'         => $merchant?->id,
                'lender_id'           => $lender?->id,
                'store_id'            => $store?->id,
                'product_id'          => $product?->id,
                'product_name'        => $productName,
                'loan_amount'         => $principal,
                'outstanding_amount'  => $principal,
                'emi_amount'          => $emi,
                'down_payment'        => $down,
                'interest_rate'       => 0,
                'tenure_months'       => $tenure,
                'installments_paid'   => 0,
                'next_due_date'       => now()->addMonth()->startOfMonth()->addDays(4)->toDateString(),
                'status'              => 'active',
                'product_category'    => $product?->category?->name,
                'approved_at'         => now(),
                'disbursed_at'        => now(),
            ]);

            $due = now()->addMonth()->startOfMonth()->addDays(4);
            for ($i = 1; $i <= $tenure; $i++) {
                LoanInstallment::create([
                    'loan_id'        => $loan->id,
                    'installment_no' => $i,
                    'due_date'       => $due->copy()->addMonths($i - 1)->toDateString(),
                    'principal'      => $emi,
                    'interest'       => 0,
                    'total_emi'      => $emi,
                    'status'         => 'UPCOMING',
                ]);
            }

            return $loan->load(['lender', 'merchant', 'installments']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Loan application submitted and disbursed (test mode).',
            'data'    => $this->formatLoanSummary($loan),
        ], 201);
    }

    #[OA\Get(
        path: '/api/v1/customer/loans/active',
        summary: 'Fetch customer active EMI loans',
        tags: ['Phase2-Customer'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Active loans')]
    )]
    public function active()
    {
        $customer = auth('customer')->user();

        $loans = Loan::with(['lender', 'merchant', 'installments'])
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['active', 'Active', 'overdue', 'Overdue'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Loan $loan) => $this->formatLoanSummary($loan));

        return response()->json([
            'success' => true,
            'customer' => [
                'id'    => $customer->id,
                'name'  => $customer->name,
                'phone' => $customer->phone,
            ],
            'data' => $loans,
        ]);
    }

    #[OA\Get(
        path: '/api/v1/customer/loans/{id}/schedule',
        summary: 'Fetch installment schedule',
        tags: ['Phase2-Customer'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Schedule')]
    )]
    public function schedule($id)
    {
        $loan = $this->ownedLoan($id);

        $paid = $loan->installments->where('status', 'PAID');
        $upcoming = $loan->installments->where('status', '!=', 'PAID');

        return response()->json([
            'success' => true,
            'data' => [
                'loan' => $this->formatLoanSummary($loan),
                'summary' => [
                    'total_disbursed'   => (float) $loan->loan_amount,
                    'total_paid'        => (float) $paid->sum('total_emi'),
                    'remaining_balance' => (float) $loan->outstanding_amount,
                    'paid_count'        => $paid->count(),
                    'remaining_count'   => $upcoming->count(),
                ],
                'schedule' => $loan->installments->map(fn (LoanInstallment $i) => [
                    'emi_no'     => $i->installment_no,
                    'due_date'   => optional($i->due_date)->format('d-M-Y'),
                    'emi'        => (float) $i->total_emi,
                    'principal'  => (float) $i->principal,
                    'interest'   => (float) $i->interest,
                    'status'     => $i->status,
                    'utr'        => $i->utr ?? '-',
                ]),
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/v1/customer/loans/{id}/soa/download',
        summary: 'Download SOA PDF blob',
        tags: ['Phase2-Customer'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'PDF file')]
    )]
    public function downloadSoa($id)
    {
        $loan = $this->ownedLoan($id);
        $pdf = $this->minimalPdf(
            "Statement of Account\nAccount: {$loan->account_no}\nCustomer: " . ($loan->customer->name ?? '') .
            "\nLoan Amount: {$loan->loan_amount}\nOutstanding: {$loan->outstanding_amount}\nGenerated: " . now()->toDateTimeString()
        );

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="SOA-' . $loan->account_no . '.pdf"',
        ]);
    }

    #[OA\Get(
        path: '/api/v1/customer/loans/{id}/foreclosure-quote',
        summary: 'Calculate 0% penalty foreclosure quote',
        tags: ['Phase2-Customer'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Foreclosure quote')]
    )]
    public function foreclosureQuote($id)
    {
        $loan = $this->ownedLoan($id);

        if (in_array(strtolower((string) $loan->status), ['closed', 'foreclosed'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Loan is already closed.',
            ], 422);
        }

        $outstanding = (float) $loan->outstanding_amount;
        $unbilledInterest = 0.0;
        $penalty = 0.0;
        $waiver = 0.0;
        $net = round($outstanding + $unbilledInterest + $penalty - $waiver, 2);

        return response()->json([
            'success' => true,
            'data' => [
                'account_no'            => $loan->account_no,
                'outstanding_principal' => $outstanding,
                'unbilled_interest'     => $unbilledInterest,
                'foreclosure_penalty'   => $penalty,
                'waiver_discount'       => $waiver,
                'net_payoff_amount'     => $net,
                'penalty_rule'          => '0% (RBI floating-rate consumer loan rule — stub)',
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/v1/customer/loans/{id}/foreclose',
        summary: 'Payoff and close loan (test mode)',
        tags: ['Phase2-Customer'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Loan closed')]
    )]
    public function foreclose($id)
    {
        $loan = $this->ownedLoan($id);

        if (in_array(strtolower((string) $loan->status), ['closed', 'foreclosed'], true)) {
            return response()->json(['success' => false, 'message' => 'Loan already closed.'], 422);
        }

        $nocRef = 'NOC-' . strtoupper(substr($loan->lender->name ?? 'LND', 0, 4)) . '-' . $loan->id;

        DB::transaction(function () use ($loan, $nocRef) {
            $loan->installments()->where('status', '!=', 'PAID')->update([
                'status'  => 'PAID',
                'utr'     => 'FORECLOSE-' . now()->format('YmdHis'),
                'paid_at' => now(),
            ]);

            $loan->update([
                'outstanding_amount' => 0,
                'installments_paid'  => $loan->tenure_months,
                'status'             => 'closed',
                'closed_at'          => now(),
                'noc_ref'            => $nocRef,
                'next_due_date'      => null,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Loan foreclosed successfully.',
            'data' => [
                'account_no' => $loan->account_no,
                'noc_ref'    => $nocRef,
                'status'     => 'closed',
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/v1/customer/loans/{id}/noc/download',
        summary: 'Download digital NOC PDF',
        tags: ['Phase2-Customer'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'PDF file')]
    )]
    public function downloadNoc($id)
    {
        $loan = $this->ownedLoan($id);

        if (!in_array(strtolower((string) $loan->status), ['closed', 'foreclosed'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'NOC available only after loan closure.',
            ], 422);
        }

        $ref = $loan->noc_ref ?? ('NOC-' . $loan->id);
        $pdf = $this->minimalPdf(
            "NO OBJECTION CERTIFICATE\nRef: {$ref}\nAccount: {$loan->account_no}\n" .
            'Customer: ' . ($loan->customer->name ?? '') . "\nProduct: {$loan->product_name}\n" .
            "Status: Full & Final Settled\nIssued: " . optional($loan->closed_at)->toDateTimeString()
        );

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $ref . '.pdf"',
        ]);
    }

    private function ownedLoan($id): Loan
    {
        $customer = auth('customer')->user();

        return Loan::with(['lender', 'merchant', 'customer', 'installments'])
            ->where('customer_id', $customer->id)
            ->findOrFail($id);
    }

    private function formatLoanSummary(Loan $loan): array
    {
        $tenure = (int) ($loan->tenure_months ?? $loan->installments->count());
        $paid = (int) ($loan->installments_paid ?? $loan->installments->where('status', 'PAID')->count());
        $percent = $tenure > 0 ? (int) round(($paid / $tenure) * 100) : 0;

        return [
            'id'                 => $loan->id,
            'account_no'         => $loan->account_no,
            'lender'             => $loan->lender?->name,
            'product'            => $loan->product_name,
            'merchant'           => $loan->merchant?->business_name ?? $loan->merchant?->name,
            'loan_amount'        => (float) $loan->loan_amount,
            'outstanding_amount' => (float) $loan->outstanding_amount,
            'emi_amount'         => (float) $loan->emi_amount,
            'next_due_date'      => optional($loan->next_due_date)->format('d-M-Y'),
            'tenure_paid'        => $paid,
            'total_tenure'       => $tenure,
            'percent_paid'       => $percent,
            'interest_rate'      => (float) $loan->interest_rate,
            'status'             => $loan->status,
            'noc_ref'            => $loan->noc_ref,
        ];
    }

    /** Minimal valid PDF without external packages (local testing). */
    private function minimalPdf(string $text): string
    {
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $lines = explode("\n", $escaped);
        $content = "BT /F1 11 Tf 50 750 Td\n";
        foreach ($lines as $i => $line) {
            if ($i > 0) {
                $content .= "0 -16 Td\n";
            }
            $content .= "({$line}) Tj\n";
        }
        $content .= "ET";

        $objects = [];
        $objects[] = '1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj';
        $objects[] = '2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj';
        $objects[] = '3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources<< /Font<< /F1 5 0 R >> >> >>endobj';
        $objects[] = '4 0 obj<< /Length ' . strlen($content) . " >>stream\n{$content}\nendstream endobj";
        $objects[] = '5 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= $obj . "\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xref}\n%%EOF";

        // Keep a copy under storage for debugging downloads
        Storage::disk('local')->put('demo/last-generated.pdf', $pdf);

        return $pdf;
    }
}
