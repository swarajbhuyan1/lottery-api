<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SlotCategory;
use Illuminate\Http\Request;

class SlotCategoryController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:slot_categories',
            'image' => 'nullable|image|max:2048',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('slot_categories', 'public');
        }

        $category = SlotCategory::create([
            'name' => $request->name,
            'image' => $imagePath,
        ]);

        return response()->json($category);
    }

    public function index()
    {
        return response()->json(SlotCategory::all());
    }
}
