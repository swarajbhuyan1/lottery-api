<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function updateStatus($id, Request $request)
    {
        $transaction = Transaction::find($id);
        $request->validate([
            'status' => 'required|in:success,failed,cancelled'
        ]);

        if ($transaction->status !== 'pending') {
            return response()->json(['message' => 'Transaction is already processed.'], 400);
        }

        DB::beginTransaction();

        try {
            // Update transaction status
            $transaction->update(['status' => $request->status]);

            // If approved, update the user's balance
            if ($request->status === 'success') {
                $transaction->user->wallet_balance += $transaction->amount;
                $transaction->user->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Transaction updated successfully.',
                'transaction' => $transaction
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update transaction.', 'error' => $e->getMessage()], 500);
        }
    }
}
