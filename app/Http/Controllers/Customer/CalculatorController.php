<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Phase 2 Screen 14 — Live EMI Calculator
 */
class CalculatorController extends Controller
{
    #[OA\Post(
        path: '/api/v1/customer/calculator/calculate',
        summary: 'Live EMI calculation breakdown',
        tags: ['Phase2-Customer'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['cart_value', 'tenure_months'],
                properties: [
                    new OA\Property(property: 'cart_value', type: 'number', example: 134900),
                    new OA\Property(property: 'down_payment', type: 'number', example: 0),
                    new OA\Property(property: 'tenure_months', type: 'integer', example: 6),
                    new OA\Property(property: 'interest_rate', type: 'number', example: 0),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'EMI breakdown'),
        ]
    )]
    public function calculate(Request $request)
    {
        $data = $request->validate([
            'cart_value'     => ['required', 'numeric', 'min:10000', 'max:2000000'],
            'down_payment'   => ['sometimes', 'numeric', 'min:0'],
            'tenure_months'  => ['required', 'integer', 'in:3,6,9,12,18,24'],
            'interest_rate'  => ['sometimes', 'numeric', 'min:0', 'max:36'],
        ]);

        $cart = (float) $data['cart_value'];
        $down = (float) ($data['down_payment'] ?? 0);
        if ($down >= $cart) {
            return response()->json([
                'success' => false,
                'message' => 'Down payment must be less than cart value.',
            ], 422);
        }

        $principal = $cart - $down;
        $tenure = (int) $data['tenure_months'];
        $rateAnnual = (float) ($data['interest_rate'] ?? 0);
        $processingFee = $rateAnnual > 0 ? round($principal * 0.01, 2) : 0.0;

        if ($rateAnnual <= 0) {
            // No-cost EMI (merchant/lender subvention) — equal principal split
            $monthlyEmi = round($principal / $tenure, 2);
            $totalInterest = 0.0;
            $interestSaved = round(($principal * 0.12 / 12) * $tenure, 2); // vs illustrative 12% p.a.
        } else {
            $r = $rateAnnual / 12 / 100;
            $monthlyEmi = $r > 0
                ? round($principal * $r * pow(1 + $r, $tenure) / (pow(1 + $r, $tenure) - 1), 2)
                : round($principal / $tenure, 2);
            $totalPayableInterest = round(($monthlyEmi * $tenure) - $principal, 2);
            $totalInterest = max(0, $totalPayableInterest);
            $interestSaved = 0.0;
        }

        $totalPayable = round(($monthlyEmi * $tenure) + $processingFee, 2);

        return response()->json([
            'success' => true,
            'data' => [
                'cart_value'       => $cart,
                'down_payment'     => $down,
                'principal'        => round($principal, 2),
                'tenure_months'    => $tenure,
                'interest_rate'    => $rateAnnual,
                'monthly_emi'      => $monthlyEmi,
                'total_interest'   => $totalInterest,
                'interest_saved'   => $interestSaved,
                'processing_fee'   => $processingFee,
                'total_payable'    => $totalPayable,
                'is_no_cost'       => $rateAnnual <= 0,
            ],
        ]);
    }
}
