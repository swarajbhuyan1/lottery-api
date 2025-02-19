<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    // Get all users with pagination
//    public function index(Request $request)
//    {
//        $users = User::paginate($request->get('per_page', 10));
//        return response()->json($users);
//    }

    // Add funds to user's wallet
    public function addFunds(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $user = User::findOrFail($id);
        $user->wallet_balance += $request->amount;
        $user->save();

        return response()->json(['message' => 'Funds added successfully', 'wallet_balance' => $user->wallet_balance]);
    }

    // Change user status (Active/Inactive)
    public function changeStatus(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->is_active = !$user->is_active; // Toggle status
        $user->save();

        return response()->json(['message' => 'User status updated successfully', 'is_active' => $user->is_active]);
    }

    // Admin changes user's password
    public function changePassword(Request $request, $id)
    {
        $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::findOrFail($id);
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json(['message' => 'Password updated successfully']);
    }

    // UserController.php
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
            'withdrawal_type' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $users = User::where('is_admin', 0)
            ->when($request->search, function ($query) use ($request) {
                $search = '%' . $request->search . '%';
                return $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('mobile', 'like', $search)
                        ->orWhere('referral_code', 'like', $search);
                });
            })
            ->when($request->withdrawal_type, function ($query) use ($request) {
                return $query->where('withdrawal_type', $request->withdrawal_type);
            })
            ->when($request->bank_name, function ($query) use ($request) {
                return $query->where('bank_name', $request->bank_name);
            })
            ->when($request->has('is_active'), function ($query) use ($request) {
                return $query->where('is_active', $request->is_active);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 10);

        return response()->json([
            'current_page' => $users->currentPage(),
            'data' => $users->items(),
            'first_page_url' => $users->url(1),
            'from' => $users->firstItem(),
            'last_page' => $users->lastPage(),
            'last_page_url' => $users->url($users->lastPage()),
            'next_page_url' => $users->nextPageUrl(),
            'path' => $users->path(),
            'per_page' => $users->perPage(),
            'prev_page_url' => $users->previousPageUrl(),
            'to' => $users->lastItem(),
            'total' => $users->total(),
        ]);
    }


}
