<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with('children')->whereNull('parent_id')->get();
        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        $category = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'parent_id' => $request->parent_id,
        ]);

        return response()->json($category, 201);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        
        $request->validate([
            'name' => 'sometimes|required|string',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        if ($request->has('name')) {
            $category->name = $request->name;
            $category->slug = Str::slug($request->name);
        }
        if ($request->has('parent_id')) {
            $category->parent_id = $request->parent_id;
        }
        $category->save();

        return response()->json($category);
    }

    public function archive(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        
        $request->validate([
            'reassign_to' => 'nullable|exists:categories,id',
        ]);

        if ($request->reassign_to && $request->reassign_to != $category->id) {
            Product::where('category_id', $category->id)->update(['category_id' => $request->reassign_to]);
            Category::where('parent_id', $category->id)->update(['parent_id' => $request->reassign_to]);
        }

        $category->status = 'archived';
        $category->save();
        $category->delete(); // soft delete

        return response()->json(['message' => 'Category archived successfully']);
    }

    public function setFinancingRules(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        
        $request->validate([
            'default_down_payment_percent' => 'nullable|numeric|min:0|max:100',
            'default_tenure_months' => 'nullable|integer|min:1',
        ]);

        $category->default_down_payment_percent = $request->default_down_payment_percent;
        $category->default_tenure_months = $request->default_tenure_months;
        $category->save();

        return response()->json(['message' => 'Financing rules updated successfully', 'category' => $category]);
    }
}
