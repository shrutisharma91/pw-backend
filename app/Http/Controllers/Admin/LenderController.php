<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lender;
use Illuminate\Http\Request;

class LenderController extends Controller
{
    public function index()
    {
        $lenders = Lender::all();
        return response()->json($lenders);
    }

    public function show($id)
    {
        $lender = Lender::findOrFail($id);
        return response()->json($lender);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:lenders',
            'api_base_url' => 'required|url',
        ]);

        $lender = Lender::create($request->all());
        return response()->json($lender, 201);
    }

    public function update(Request $request, $id)
    {
        $lender = Lender::findOrFail($id);
        $lender->update($request->all());
        return response()->json($lender);
    }

    public function toggle($id)
    {
        $lender = Lender::findOrFail($id);
        $lender->status = $lender->status === 'active' ? 'inactive' : 'active';
        $lender->save();

        return response()->json(['message' => 'Lender toggled', 'lender' => $lender]);
    }

    public function testConnection(Request $request, $id)
    {
        $lender = Lender::findOrFail($id);
        // In a real app we'd use Laravel's Http client to make a call to $lender->api_base_url
        // Http::withHeaders(['Auth' => ...])->post($lender->api_base_url . '/test', $request->payload);
        return response()->json(['status' => 'success', 'message' => 'Connection successful (simulated)', 'lender' => $lender->name]);
    }
}
