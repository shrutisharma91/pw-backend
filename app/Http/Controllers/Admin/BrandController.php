<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index()
    {
        $brands = Brand::all();
        return response()->json($brands);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:brands',
            'logo_url' => 'nullable|url',
            'status' => 'nullable|string|in:approved,pending,rejected',
        ]);

        $brand = Brand::create($request->all());

        return response()->json($brand, 201);
    }

    public function update(Request $request, $id)
    {
        $brand = Brand::findOrFail($id);
        
        $request->validate([
            'name' => 'sometimes|required|string|unique:brands,name,'.$id,
            'logo_url' => 'nullable|url',
            'status' => 'nullable|string|in:approved,pending,rejected',
        ]);

        $brand->update($request->all());

        return response()->json($brand);
    }
}
