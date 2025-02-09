<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Slot;
use Illuminate\Http\Request;

class AdminSlotController extends Controller
{
    public function index(Request $request)
    {
        $slots = Slot::paginate($request->get('per_page', 10));
        return response()->json($slots);
    }

    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1000',
            'member_limit' => 'required|integer|min:2',
            'winning_percentage' => 'required|numeric|between:1,100',
        ]);

        $slot = Slot::create([
            'amount' => $request->amount,
            'member_limit' => $request->member_limit,
            'winning_percentage' => $request->winning_percentage,
            'start_time' => now(),
            'end_time' => now()->addDay(),
            'status' => 'active'
        ]);

        return response()->json($slot);
    }

    // Update slot details
    public function update(Request $request, $id)
    {
        $slot = Slot::findOrFail($id);

        $request->validate([
            'amount' => 'sometimes|numeric|min:1000',
            'member_limit' => 'sometimes|integer|min:2',
            'winning_percentage' => 'sometimes|numeric|between:1,100',
        ]);

        $slot->update($request->only(['amount', 'member_limit', 'winning_percentage', 'start_time', 'end_time']));

        return response()->json(['message' => 'Slot updated successfully', 'slot' => $slot]);
    }

    // Change status of the slot (active/inactive)
    public function changeStatus(Request $request, $id)
    {
        $slot = Slot::findOrFail($id);
        $request->validate(['status' => 'required|in:active,inactive']);

        $slot->update(['status' => $request->status]);

        return response()->json(['message' => 'Status updated successfully', 'slot' => $slot]);
    }

    // Soft delete a slot
    public function softDelete($id)
    {
        $slot = Slot::findOrFail($id);
        $slot->delete();

        return response()->json(['message' => 'Slot deleted successfully']);
    }

    // Restore a soft-deleted slot
    public function restore($id)
    {
        $slot = Slot::withTrashed()->findOrFail($id);
        $slot->restore();

        return response()->json(['message' => 'Slot restored successfully']);
    }

    // Permanently delete a slot
    public function forceDelete($id)
    {
        $slot = Slot::withTrashed()->findOrFail($id);
        $slot->forceDelete();

        return response()->json(['message' => 'Slot permanently deleted']);
    }
}
