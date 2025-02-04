<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Models\Otp;
use App\Models\User;
use App\Models\Referral;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email:rfc,dns|unique:users,email',
                'mobile' => 'required|string|unique:users,mobile',
                'password' => 'required|string|min:6|confirmed',
                'otp_method' => 'required|in:email,mobile'
            ]);

            // Generate OTP
            $otp = mt_rand(100000, 999999);

            // Store OTP
            Otp::updateOrCreate(
                [$request->otp_method => $request->input($request->otp_method)],
                [
                    'name' => $request->name,
                    'mobile' => $request->mobile,
                    'email' => $request->email,
                    'code' => $otp,
                    'method' => $request->otp_method,
                    'expires_at' => now()->addMinutes(10)
                ]
            );

            // Handle referral
            if ($request->referral_code) {
                $referrer = User::where('referral_code', $request->referral_code)->first();
                if ($referrer) {
                    Referral::create([
                        'referrer_id' => $referrer->id,
                        'referee_id' => null, // Will update after verification
                        'commission' => config('referral.commission'),
                        'status' => 'pending'
                    ]);
                }
            }

            return response()->json(['message' => 'OTP sent successfully']);

        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
                'message' => 'Validation failed'
            ], 422);
        }
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'code' => 'required|digits:6',
            'method' => 'required|in:email,mobile',
            'mobile' => 'required',
            'password' => 'required|string|min:6|confirmed'
        ]);

        $otp = Otp::where('code', $request->code)
            ->where('method', $request->method)
            ->where('mobile', $request->mobile)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otp) {
            return response()->json(['error' => 'Invalid OTP'], 401);
        }

        // Create user
        $user = User::create([
            'name' => $otp->name,
            'email' => $otp->email,
            'mobile' => $otp->mobile,
            'password' => Hash::make($request->password),
            'referral_code' => $this->generateUniqueReferralCode()
        ]);

        // Update referral
        if ($request->referral_code) {
            Referral::where('referral_code', $request->referral_code)
                ->whereNull('referee_id')
                ->update(['referee_id' => $user->id]);
        }

        // Delete OTP
        $otp->delete();

        return response()->json([
            'message' => 'User registered successfully',
            'token' => $user->createToken('authToken')->plainTextToken
        ]);
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required_without:mobile|email',
                'mobile' => 'required_without:email|string',
                'password' => 'required'
            ]);

            // Find user by email or mobile
            $user = User::where('email', $request->email)
                ->orWhere('mobile', $request->mobile)
                ->first();

            // Validate user existence and password
            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json(['error' => 'Invalid credentials'], 401);
            }

            // Revoke existing tokens
            $user->tokens()->delete();

            return response()->json([
                'message' => 'Login successful',
                'token' => $user->createToken('authToken')->plainTextToken,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'mobile' => $user->mobile,
                    'wallet_balance' => $user->wallet_balance
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
                'message' => 'Validation failed'
            ], 422);
        }
    }

    private function generateUniqueReferralCode()
    {
        do {
            $code = strtoupper(substr(md5(uniqid()), 0, 8));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }
}
