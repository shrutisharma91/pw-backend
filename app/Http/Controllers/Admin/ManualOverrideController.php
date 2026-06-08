<?php

namespace App\Http\Controllers\Admin;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LoanApplication;
use App\Models\Disbursal;

class ManualOverrideController extends Controller
{
    // Screen 35: High-privilege actions
    #[OA\Post(
        path: "/api/v1/admin/loans/overrides/{id}/force-approve",
        summary: "forceApprove ManualOverride",
        security: [["sanctum" => []]],
        tags: ["ManualOverride"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true)
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]

    public function forceApprove(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string',
            'approved_by_secondary' => 'required|integer' // Dual approval
        ]);

        $loan = LoanApplication::findOrFail($id);
        $loan->status = 'Approved';
        $loan->save();

        return response()->json(['message' => 'Loan force-approved successfully', 'loan' => $loan]);
    }

    #[OA\Post(
        path: "/api/v1/admin/loans/overrides/{id}/override-rejection",
        summary: "overrideRejection ManualOverride",
        security: [["sanctum" => []]],
        tags: ["ManualOverride"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true)
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]

    public function overrideRejection(Request $request, $id)
    {
        $request->validate([
            'new_lender_id' => 'required|exists:lenders,id',
            'reason' => 'required|string'
        ]);

        $loan = LoanApplication::findOrFail($id);
        $loan->lender_id = $request->new_lender_id;
        $loan->status = 'Initiated'; // Restarting the flow
        $loan->save();

        return response()->json(['message' => 'Lender rerouted successfully', 'loan' => $loan]);
    }

    #[OA\Post(
        path: "/api/v1/admin/loans/overrides/{id}/trigger-disbursal",
        summary: "triggerDisbursal ManualOverride",
        security: [["sanctum" => []]],
        tags: ["ManualOverride"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true)
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]

    public function triggerDisbursal(Request $request, $id)
    {
        $request->validate([
            'bank_account_verified' => 'required|boolean:true',
            'reason' => 'required|string'
        ]);

        $loan = LoanApplication::findOrFail($id);
        $loan->status = 'Disbursed';
        $loan->save();

        $disbursal = Disbursal::create([
            'loan_application_id' => $loan->id,
            'lender_id' => $loan->lender_id,
            'amount' => $loan->amount,
            'status' => 'Initiated'
        ]);

        return response()->json(['message' => 'Manual disbursal triggered', 'disbursal' => $disbursal]);
    }

    #[OA\Post(
        path: "/api/v1/admin/loans/overrides/{id}/refund",
        summary: "refund ManualOverride",
        security: [["sanctum" => []]],
        tags: ["ManualOverride"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true)
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]

    public function refund(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string',
            'finance_approved_by' => 'required|integer'
        ]);

        $loan = LoanApplication::findOrFail($id);
        $loan->status = 'Cancelled';
        $loan->save();

        return response()->json(['message' => 'Refund/Reversal initiated successfully']);
    }
}