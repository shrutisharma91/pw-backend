<?php

namespace App\Modules\Lender\Http\Controllers;

use App\Models\LenderRule;
use App\Models\LoanApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RuleEngineController extends LenderBaseController
{
    public function index()
    {
        $rules = LenderRule::query()
            ->where('lender_id', $this->scopedLenderId())
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['success' => true, 'data' => $rules]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'conditions' => 'required|array',
        ]);

        // Never trust request lender_id — always scope from auth/middleware.
        $rule = LenderRule::create([
            'name' => $request->name,
            'conditions' => $request->conditions,
            'lender_id' => $this->scopedLenderId(),
            'status' => 'draft',
            'version' => 1,
            'created_by' => Auth::id(),
        ]);

        return response()->json(['success' => true, 'data' => $rule], 201);
    }

    public function update(Request $request, int $id)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'conditions' => 'sometimes|array',
        ]);

        $rule = LenderRule::query()
            ->where('lender_id', $this->scopedLenderId())
            ->where('id', $id)
            ->firstOrFail();

        $rule->fill($request->only(['name', 'conditions']));
        $rule->save();

        return response()->json(['success' => true, 'data' => $rule]);
    }

    public function activate(int $id)
    {
        $rule = LenderRule::query()
            ->where('lender_id', $this->scopedLenderId())
            ->where('id', $id)
            ->firstOrFail();

        $rule->update(['status' => 'active']);

        return response()->json(['success' => true, 'data' => $rule->fresh()]);
    }

    public function simulate(Request $request)
    {
        $rules = LenderRule::query()
            ->where('lender_id', $this->scopedLenderId())
            ->where('status', 'active')
            ->get();

        $apps = LoanApplication::query()
            ->where('lender_id', $this->scopedLenderId())
            ->limit(100)
            ->get();

        $matched = 0;
        foreach ($apps as $app) {
            $cibil = (int) ($app->application_payload['cibil'] ?? 0);
            foreach ($rules as $rule) {
                $min = (int) ($rule->conditions['min_cibil'] ?? 0);
                if ($cibil >= $min) {
                    $matched++;
                    break;
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'applications_sampled' => $apps->count(),
                'would_match' => $matched,
                'rules_evaluated' => $rules->count(),
            ],
        ]);
    }
}
