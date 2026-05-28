<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LenderWaterfall;
use Illuminate\Http\Request;

class LenderWaterfallController extends Controller
{
    public function index()
    {
        $waterfalls = LenderWaterfall::all();
        return response()->json($waterfalls);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'priority_order' => 'required|array',
        ]);

        $waterfall = LenderWaterfall::create($request->all());
        return response()->json($waterfall, 201);
    }

    public function update(Request $request, $id)
    {
        $waterfall = LenderWaterfall::findOrFail($id);
        $waterfall->update($request->all());
        return response()->json($waterfall);
    }

    public function simulate(Request $request)
    {
        $payload = $request->input('payload');
        // Logic to parse payload, check category, geo, rules, and select lender
        // For simulation, we return a mock decision
        return response()->json([
            'decision' => 'approved',
            'selected_lender_id' => 1,
            'reason' => 'Matched Priority 1 Waterfall and passed basic risk rules',
            'payload_received' => $payload
        ]);
    }
}
