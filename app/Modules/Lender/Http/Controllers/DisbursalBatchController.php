<?php

namespace App\Modules\Lender\Http\Controllers;

use App\Models\SettlementBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DisbursalBatchController extends LenderBaseController
{
    public function index()
    {
        $batches = SettlementBatch::query()
            ->where('lender_id', $this->scopedLenderId())
            ->orderByDesc('date')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $batches]);
    }

    public function release(Request $request, int $id)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $role = strtolower((string) $user->role);

        // Dual-signature: lender_ops must hold disbursal_authorizer. Super Admin may release for support.
        if ($role === 'lender_ops' && !$user->can('disbursal_authorizer')) {
            return response()->json([
                'success' => false,
                'message' => 'Dual-signature required: disbursal_authorizer permission needed.',
                'code' => 'dual_signature_required',
            ], 403);
        }

        $batch = SettlementBatch::query()
            ->where('lender_id', $this->scopedLenderId())
            ->where('id', $id)
            ->firstOrFail();

        if (strtolower((string) $batch->status) === 'released') {
            return response()->json([
                'success' => true,
                'message' => 'Batch already released.',
                'data' => $batch,
            ]);
        }

        $batch->update([
            'status' => 'Released',
            'utr_number' => $batch->utr_number ?: ('UTR-' . now()->format('YmdHis')),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Batch released.',
            'data' => $batch->fresh(),
        ]);
    }
}
