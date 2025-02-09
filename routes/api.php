<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminSlotController;
use App\Http\Controllers\Api\SlotController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TransactionController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

// OTP Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('user')->group(function () {
        Route::post('add-balance', [WalletController::class, 'addBalance']);
        Route::post('join-slot', [SlotController::class, 'join']);
//        get participants
        Route::get('/slots/{slot}/participants', [SlotController::class, 'participants']);
        // Add/update withdrawal details
        Route::post('withdrawal-details', [UserController::class, 'updateWithdrawalDetails']);
        // Get withdrawal details
        Route::get('withdrawal-details', [UserController::class, 'getWithdrawalDetails']);
    });
//    Razorpay
//    Route::post('/wallet/create-payment', [WalletController::class, 'createPayment']);
//    Route::post('/wallet/verify-payment', [WalletController::class, 'verifyPayment']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
//    Route::get('/dashboard', [AdminSlotController::class, 'store']);
//    Slot api
    Route::prefix('slots')->group(function () {
        Route::get('/', [AdminSlotController::class, 'index']);
        Route::post('/', [AdminSlotController::class, 'store']);
        Route::put('/{id}', [AdminSlotController::class, 'update']);
        Route::patch('/{id}/status', [AdminSlotController::class, 'changeStatus']);
        Route::delete('/{id}', [AdminSlotController::class, 'softDelete']);
        Route::post('/{id}/restore', [AdminSlotController::class, 'restore']);
        Route::delete('/{id}/force-delete', [AdminSlotController::class, 'forceDelete']);
    });
    // User api
    Route::prefix('users')->group(function () {
        Route::get('/', [AdminUserController::class, 'index']);
        Route::patch('/{id}/add-funds', [AdminUserController::class, 'addFunds']);
        Route::patch('/{id}/status', [AdminUserController::class, 'changeStatus']);
        Route::patch('/{id}/change-password', [AdminUserController::class, 'changePassword']);
    });
    Route::prefix('transactions')->group(function () {
        Route::post('/{transaction}/update-status', [TransactionController::class, 'updateStatus']);
    });

});
