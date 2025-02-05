<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function addBalance(Request $request)
    {
        $request->validate(['amount' => 'required|numeric|min:1000']);

        $user = $request->user();
        $user->wallet_balance += $request->amount;
        $user->save();

        return response()->json([
            'message' => 'Balance added successfully.',
            'new_balance' => $user->wallet_balance
        ]);
    }
}
