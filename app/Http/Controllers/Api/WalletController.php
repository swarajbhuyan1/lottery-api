<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Razorpay\Api\Api;

class WalletController extends Controller
{
    public function addBalance(Request $request)
    {
        $request->validate(['amount' => 'required|numeric|min:1000']);

        $user = $request->user();
        $user->wallet_balance += $request->amount;
        $user->save();

        $pendingReferrals = Referral::where('referee_id', $user->id)
            ->where('status', 'pending')
            ->get();

        foreach ($pendingReferrals as $referral) {
            // Update referral status
            $referral->update(['status' => 'credited']);

            // Credit referrer's wallet
            $referrer = User::find($referral->referrer_id);
            $referrer->wallet_balance += $referral->commission;
            $referrer->save();
        }

        return response()->json([
            'message' => 'Balance added successfully.',
            'new_balance' => $user->wallet_balance
        ]);
    }

//Razorpay
//    public function createPayment(Request $request)
//    {
//        $request->validate(['amount' => 'required|numeric|min:1000']);
//
//        $api = new Api(env('RAZORPAY_KEY'), env('RAZORPAY_SECRET'));
//
//        $order = $api->order->create([
//            'amount' => $request->amount * 100, // Convert to paise
//            'currency' => 'INR',
//            'payment_capture' => 1
//        ]);
//
//        return response()->json([
//            'order_id' => $order['id'],
//            'amount' => $order['amount']
//        ]);
//    }
//
//    public function verifyPayment(Request $request)
//    {
//        $request->validate([
//            'payment_id' => 'required',
//            'order_id' => 'required',
//            'signature' => 'required'
//        ]);
//
//        $api = new Api(env('RAZORPAY_KEY'), env('RAZORPAY_SECRET'));
//
//        $attributes = [
//            'razorpay_order_id' => $request->order_id,
//            'razorpay_payment_id' => $request->payment_id,
//            'razorpay_signature' => $request->signature
//        ];
//
//        try {
//            $api->utility->verifyPaymentSignature($attributes);
//
//            DB::beginTransaction();
//
//            $user = $request->user();
//            $amount = $request->amount;
//
//            // Update user's wallet
//            $user->wallet_balance += $amount;
//            $user->save();
//
//            // Store transaction details
//            Transaction::create([
//                'user_id' => $user->id,
//                'amount' => $amount,
//                'type' => 'deposit',
//                'status' => 'success',
//                'transaction_id' => $request->payment_id,
//                'method' => 'razorpay'
//            ]);
//
//            // Handle referral bonus
//            $this->processReferralBonus($user);
//
//            DB::commit();
//
//            return response()->json([
//                'message' => 'Payment verified & balance added.',
//                'new_balance' => $user->wallet_balance
//            ]);
//
//        } catch (\Exception $e) {
//            DB::rollBack();
//            return response()->json([
//                'message' => 'Payment verification failed.',
//                'error' => $e->getMessage()
//            ], 400);
//        }
//    }
//
//    private function processReferralBonus(User $user)
//    {
//        $referral = Referral::where('referee_id', $user->id)
//            ->where('status', 'pending')
//            ->first();
//
//        if ($referral) {
//            $referrer = User::find($referral->referrer_id);
//
//            if ($referrer) {
//                $referrer->wallet_balance += 200;
//                $referrer->save();
//
//                $referral->update(['status' => 'credited']);
//
//                // Log the referral transaction
//                Transaction::create([
//                    'user_id' => $referrer->id,
//                    'amount' => 200,
//                    'type' => 'deposit',
//                    'status' => 'success',
//                    'transaction_id' => null,
//                    'method' => 'referral_bonus'
//                ]);
//            }
//        }
//    }

}
