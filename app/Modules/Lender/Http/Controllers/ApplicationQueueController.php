<?php

namespace App\Modules\Lender\Http\Controllers;

use App\Models\LoanApplication;
use Illuminate\Http\Request;

class ApplicationQueueController extends LenderBaseController
{
    public function queue(Request $request)
    {
        $lenderId = $this->scopedLenderId();

        $query = LoanApplication::with(['customer', 'merchant', 'store'])
            ->where('lender_id', $lenderId);

        if ($request->filled('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('min_amount')) {
            $query->where('amount', '>=', $request->min_amount);
        }
        if ($request->filled('max_amount')) {
            $query->where('amount', '<=', $request->max_amount);
        }
        if ($request->filled('cibil_band')) {
            $band = $request->cibil_band;
            $query->whereRaw("json_extract(application_payload, '$.cibil') IS NOT NULL");
            if ($band === '700+') {
                $query->whereRaw("CAST(json_extract(application_payload, '$.cibil') AS INTEGER) >= 700");
            } elseif ($band === '650-699') {
                $query->whereRaw("CAST(json_extract(application_payload, '$.cibil') AS INTEGER) BETWEEN 650 AND 699");
            } elseif ($band === '<650') {
                $query->whereRaw("CAST(json_extract(application_payload, '$.cibil') AS INTEGER) < 650");
            }
        }

        $paginated = $query->orderByDesc('created_at')->paginate((int) $request->get('per_page', 20));

        $paginated->getCollection()->transform(function (LoanApplication $app) {
            $payload = $app->application_payload ?? [];
            return [
                'id' => $app->id,
                'customer_name' => $app->customer?->name,
                'merchant_name' => $app->merchant?->business_name,
                'amount' => $app->amount,
                'status' => $app->status,
                'cibil' => $payload['cibil'] ?? null,
                'sla_breached' => $app->sla_breached,
                'sla_timer_minutes' => $app->sla_breached ? 0 : 20,
                'created_at' => $app->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function show(int $id)
    {
        $app = $this->findLoanApplication($id);
        $app->load(['customer', 'merchant', 'store', 'timelineEvents', 'documents']);

        return response()->json(['success' => true, 'data' => $app]);
    }
}
