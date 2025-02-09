<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function updateWithdrawalDetails(Request $request)
    {
        $request->validate([
            'withdrawal_type' => 'required|string|in:phonepe,paytm,gpay,bharatpay,bank',
            'withdrawal_id' => 'required|string', // UPI ID or Mobile No or Account No
            'bank_name' => 'required_if:withdrawal_type,bank|string|nullable',
            'account_number' => 'required_if:withdrawal_type,bank|string|nullable',
            'ifsc_code' => 'required_if:withdrawal_type,bank|string|nullable',
        ]);

        $user = auth()->user(); // Get the logged-in user
        $user->withdrawal_type = $request->withdrawal_type;
        $user->withdrawal_id = $request->withdrawal_id;
        $user->bank_name = $request->bank_name;
        $user->account_number = $request->account_number;
        $user->ifsc_code = $request->ifsc_code;
        $user->save();

        return response()->json(['message' => 'Withdrawal details updated successfully', 'user' => $user]);
    }
    public function getWithdrawalDetails()
    {
        $user = auth()->user();
        return response()->json([
            'withdrawal_type' => $user->withdrawal_type,
            'withdrawal_id' => $user->withdrawal_id,
            'bank_name' => $user->bank_name,
            'account_number' => $user->account_number,
            'ifsc_code' => $user->ifsc_code
        ]);
    }
}
