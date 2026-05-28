<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LenderRule;
use Illuminate\Http\Request;

class LenderRuleController extends Controller
{
    public function index()
    {
        $rules = LenderRule::with('lender')->get();
        return response()->json($rules);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'conditions' => 'required|array',
            'lender_id' => 'required|exists:lenders,id',
        ]);

        $rule = LenderRule::create($request->all());
        return response()->json($rule, 201);
    }

    public function update(Request $request, $id)
    {
        $rule = LenderRule::findOrFail($id);
        $rule->update($request->all());
        return response()->json($rule);
    }

    public function archive($id)
    {
        $rule = LenderRule::findOrFail($id);
        $rule->status = 'archived';
        $rule->save();
        
        return response()->json(['message' => 'Rule archived', 'rule' => $rule]);
    }
}
