<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Slot;
use Illuminate\Http\Request;

class AdminSlotController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1000',
            'member_limit' => 'required|integer|min:2'
        ]);

        $slot = Slot::create([
            'amount' => $request->amount,
            'member_limit' => $request->member_limit,
            'start_time' => now(),
            'end_time' => now()->addDay(),
            'status' => 'active'
        ]);

        return response()->json($slot);
    }
}
