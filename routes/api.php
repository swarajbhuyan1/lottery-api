<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminSlotController;
use App\Http\Controllers\Api\SlotController;
use App\Http\Controllers\Api\WalletController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

// OTP Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('join-slot', [SlotController::class, 'join']);
    Route::post('add-balance', [WalletController::class, 'addBalance']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
//    Route::get('/dashboard', [AdminSlotController::class, 'store']);
    Route::post('/slots', [AdminSlotController::class, 'store']);
});
