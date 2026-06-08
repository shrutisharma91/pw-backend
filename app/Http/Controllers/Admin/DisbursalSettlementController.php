<?php

namespace App\Http\Controllers\Admin;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Disbursal;
use App\Models\SettlementBatch;
use App\Models\SettlementEntry;
use App\Models\SettlementDispute;

class DisbursalSettlementController extends Controller
{
    // Screen 36: Disbursal & Settlement Queue
    #[OA\Get(
        path: "/api/v1/admin/disbursals/pending",
        summary: "pendingDisbursals DisbursalSettlement",
        security: [["sanctum" => []]],
        tags: ["DisbursalSettlement"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function pendingDisbursals(Request $request)
    {
        $query = Disbursal::with(['loanApplication', 'lender'])->where('status', 'Pending');
        
        if ($request->has('lender_id')) $query->where('lender_id', $request->lender_id);

        return response()->json($query->get());
    }

    #[OA\Post(
        path: "/api/v1/admin/disbursals/trigger-batch",
        summary: "triggerBatchDisbursal DisbursalSettlement",
        security: [["sanctum" => []]],
        tags: ["DisbursalSettlement"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function triggerBatchDisbursal(Request $request)
    {
        $request->validate(['lender_id' => 'required|exists:lenders,id']);

        Disbursal::where('lender_id', $request->lender_id)
                 ->where('status', 'Pending')
                 ->update(['status' => 'Initiated']);

        return response()->json(['message' => 'Batch disbursal triggered']);
    }

    #[OA\Get(
        path: "/api/v1/admin/settlements/batches",
        summary: "settlementBatches DisbursalSettlement",
        security: [["sanctum" => []]],
        tags: ["DisbursalSettlement"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function settlementBatches(Request $request)
    {
        $batches = SettlementBatch::with('lender')->orderBy('date', 'desc')->paginate(20);
        return response()->json($batches);
    }


    #[OA\Get(
        path: "/api/v1/admin/settlements/batches/{batch_id}/entries",
        summary: "settlementEntries DisbursalSettlement",
        security: [["sanctum" => []]],
        tags: ["DisbursalSettlement"],
        parameters: [
            new OA\Parameter(name: "batch_id", in: "path", required: true)
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function settlementEntries($batch_id)
    {
        $entries = SettlementEntry::where('settlement_batch_id', $batch_id)->with(['merchant', 'loanApplication'])->get();
        return response()->json($entries);
    }


    #[OA\Get(
        path: "/api/v1/admin/settlements/batches/{batch_id}/download",
        summary: "downloadSettlement DisbursalSettlement",
        security: [["sanctum" => []]],
        tags: ["DisbursalSettlement"],
        parameters: [
            new OA\Parameter(name: "batch_id", in: "path", required: true)
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function downloadSettlement($batch_id)
    {
        return response()->json(['message' => 'Settlement file generated. Link will be sent.']);
    }


    #[OA\Post(
        path: "/api/v1/admin/settlements/entries/{entry_id}/dispute",
        summary: "disputeSettlement DisbursalSettlement",
        security: [["sanctum" => []]],
        tags: ["DisbursalSettlement"],
        parameters: [
            new OA\Parameter(name: "entry_id", in: "path", required: true)
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function disputeSettlement(Request $request, $entry_id)
    {
        $request->validate(['reason' => 'required|string']);

        $dispute = SettlementDispute::create([
            'settlement_entry_id' => $entry_id,
            'reason' => $request->reason,
            'status' => 'Open'
        ]);

        SettlementEntry::where('id', $entry_id)->update(['status' => 'disputed']);

        return response()->json(['message' => 'Dispute logged successfully', 'dispute' => $dispute]);
    }
}