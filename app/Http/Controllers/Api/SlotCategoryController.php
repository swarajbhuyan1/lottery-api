<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SlotCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SlotCategoryController extends Controller
{
//    public function store(Request $request)
//    {
//        $request->validate([
//            'name' => 'required|string|unique:slot_categories',
//            'image' => 'nullable|image|max:2048',
//        ]);
//
//        $imagePath = null;
//        if ($request->hasFile('image')) {
//            $imagePath = $request->file('image')->store('slot-categories', 'public');
//        }
//
//        $category = SlotCategory::create([
//            'name' => $request->name,
//            'image' => $imagePath ? 'storage/' . $imagePath : null,
//            'status' => 1, // Default active status
//        ]);
//
//        return response()->json($category, 201);
//    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:slot_categories',
            'image' => 'nullable|string', // Accepts the Base64 encoded image string
        ]);

        $imagePath = null;
        if ($request->has('image')) {
            // Decode the base64 string and create the image file
            $imageData = $request->input('image');

            // Remove the base64 image prefix, like "data:image/jpeg;base64,"
            if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $matches)) {
                $imageData = substr($imageData, strpos($imageData, ',') + 1);
                $imageData = base64_decode($imageData);
                $extension = $matches[1]; // Get the image extension (jpeg, png, etc.)

                // Generate a unique name for the image
                $imageName = 'slot_category_' . time() . '.' . $extension;

                // Store the image in the 'slot-categories' folder in the public disk
                // Use the correct file name and store it
                Storage::disk('public')->put('slot-categories/' . $imageName, $imageData);

                // Set the correct image path to be used in the database
                $imagePath = 'storage/slot-categories/' . $imageName;
            }
        }

        // Create the SlotCategory entry
        $category = SlotCategory::create([
            'name' => $request->name,
            'image' => $imagePath ? asset($imagePath) : null, // Return the full URL link
            'status' => $request->status, // Ensure status is passed
            'multipliers' => $request->multipliers, // Ensure status is passed
        ]);

        return response()->json($category, 201);
    }





    public function show($id)
    {
        $category = SlotCategory::findOrFail($id);
        return response()->json($category);
    }

    public function update(Request $request, $id)
    {
        $category = SlotCategory::findOrFail($id);

        $request->validate([
            'name' => 'required|string|unique:slot_categories,name,' . $id,
            'image' => 'nullable|image|max:2048',
            'status' => 'nullable|boolean',
        ]);

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('slot-categories', 'public');
            $category->image = 'storage/' . $imagePath;
        }

        $category->name = $request->name;
        if ($request->has('status')) {
            $category->status = $request->status;
        }

        $category->save();

        return response()->json($category);
    }

    public function destroy($id)
    {
        $category = SlotCategory::findOrFail($id);
        $category->delete();

        return response()->json(['message' => 'Slot category deleted successfully.']);
    }

    public function restore($id)
    {
        $category = SlotCategory::withTrashed()->findOrFail($id);
        $category->restore();

        return response()->json(['message' => 'Slot category restored successfully.', 'data' => $category]);
    }

    public function forceDelete($id)
    {
        $category = SlotCategory::withTrashed()->findOrFail($id);
        $category->forceDelete();

        return response()->json(['message' => 'Slot category permanently deleted.']);
    }


    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|boolean',
        ]);

        $slotCategories = SlotCategory::query()
            ->when($request->search, function ($query) use ($request) {
                $search = '%' . $request->search . '%';
                return $query->where('name', 'like', $search);
            })
            ->when($request->has('status'), function ($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 10);

        return response()->json([
            'current_page' => $slotCategories->currentPage(),
            'data' => $slotCategories->items(),
            'first_page_url' => $slotCategories->url(1),
            'from' => $slotCategories->firstItem(),
            'last_page' => $slotCategories->lastPage(),
            'last_page_url' => $slotCategories->url($slotCategories->lastPage()),
            'next_page_url' => $slotCategories->nextPageUrl(),
            'path' => $slotCategories->path(),
            'per_page' => $slotCategories->perPage(),
            'prev_page_url' => $slotCategories->previousPageUrl(),
            'to' => $slotCategories->lastItem(),
            'total' => $slotCategories->total(),
        ]);
    }

}
