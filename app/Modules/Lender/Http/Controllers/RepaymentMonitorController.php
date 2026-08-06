<?php

namespace App\Modules\Lender\Http\Controllers;

use App\Models\Collection;
use App\Models\LoanApplication;
use Illuminate\Http\Request;

class RepaymentMonitorController extends LenderBaseController
{
    public function dpdBuckets()
    {
        $lenderId = $this->scopedLenderId();

        $buckets = ['0-30', '31-60', '61-90', '90+'];
        $result = [];

        foreach ($buckets as $bucket) {
            $query = Collection::query()
                ->where('dpd_bucket', $bucket)
                ->whereHas('loanApplication', fn ($q) => $q->where('lender_id', $lenderId));

            $result[] = [
                'bucket' => $bucket,
                'count' => $query->count(),
                'value' => (float) (clone $query)->sum('overdue_amount'),
            ];
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function delinquent(Request $request)
    {
        $lenderId = $this->scopedLenderId();

        $query = Collection::with(['loanApplication.customer'])
            ->whereHas('loanApplication', fn ($q) => $q->where('lender_id', $lenderId));

        if ($request->filled('dpd_bucket')) {
            $query->where('dpd_bucket', $request->dpd_bucket);
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderByDesc('overdue_amount')->paginate(20),
        ]);
    }

    public function tagNpa(int $loan_id)
    {
        $app = LoanApplication::query()
            ->where('lender_id', $this->scopedLenderId())
            ->where('id', $loan_id)
            ->firstOrFail();

        $collection = Collection::firstOrCreate(
            ['loan_application_id' => $app->id],
            ['dpd_bucket' => '0-30', 'overdue_amount' => 0, 'status' => 'Pending']
        );

        // Tagging NPA moves the account into the severe delinquency bucket.
        $collection->update([
            'npa_status' => 'NPA',
            'dpd_bucket' => '90+',
            'status' => 'NPA',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Loan tagged as NPA.',
            'data' => $collection->fresh(['loanApplication']),
        ]);
    }
}
