<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    // Get all users with pagination
    public function index(Request $request)
    {
        $users = User::paginate($request->get('per_page', 10));
        return response()->json($users);
    }

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

}
