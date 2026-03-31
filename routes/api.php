<?php

use App\Http\Controllers\Api\Admin\AuctionModerationController;
use App\Http\Controllers\Api\User\ProfileController;
use App\Http\Controllers\Api\User\NotificationController;
use App\Http\Controllers\Api\User\BidController;
use App\Http\Controllers\Api\User\JwtAuthController;
use App\Http\Controllers\Api\User\SanctumController;
use App\Http\Controllers\Api\User\PaymentController;
use App\Http\Controllers\Api\User\AuctionController;
use App\Http\Controllers\Api\User\CategoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::prefix('auth')->controller(JwtAuthController::class)->group(function () {

    Route::post('register', [JwtAuthController::class, 'register']);
    Route::post('login', [JwtAuthController::class, 'login']);
    Route::middleware('auth:jwt')->group(function () {
        Route::delete('logout', [JwtAuthController::class, 'logout']);
        Route::post('refresh', [JwtAuthController::class, 'refresh']);
    });
});

Route::middleware('auth:jwt')->group(function () {
    Route::get('users', [SanctumController::class, 'index']);
});


// Sanctum Authentication Routes
// Route::controller(SanctumController::class)->group(function () {
//     Route::post('/register', 'register');
//     Route::post('/verify-OTP', 'verifyOTP')->middleware('throttle:otp-limiter');
//     Route::post('/resend-OTP', 'reSendOTP')->middleware('throttle:otp-limiter');
//     Route::post('/login', 'login');
//     Route::post('/logout', 'logout')->middleware('auth:sanctum');
//     Route::get('auth/google', 'redirectToGoogle');
//     Route::get('auth/google/callback', 'handleGoogleCallback');
// });


// payment routes
Route::post('/stripe/checkout', [PaymentController::class, 'checkout']);
Route::post('/stripe/handleWebhook', [PaymentController::class, 'handleWebhook']);

// bid routes
Route::Post('/bids', [BidController::class, 'store']);

// auction routes
Route::group(['middleware' => 'auth:jwt'], function () {
    Route::get('/auctions', [AuctionController::class, 'index']);
    Route::post('/auctions', [AuctionController::class, 'store']);
    Route::get('/auctions/{id}', [AuctionController::class, 'show']);
});

// category routes
Route::post('/categories', [CategoryController::class, 'store']);

// admin auction moderation routes
Route::prefix('admin/auctions')->group(function () {
    Route::post('{id}/approve', [AuctionModerationController::class, 'approve']);
    Route::post('{id}/reject', [AuctionModerationController::class, 'reject']);
});

// notifications routes
Route::middleware('auth:jwt')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
});

// profile routes
Route::group(['middleware' => 'auth:jwt', 'prefix' => 'me'], function () {
    Route::get('/', [ProfileController::class, 'show']);
    Route::put('/', [ProfileController::class, 'update']);
    Route::put('password', [ProfileController::class, 'changePassword']);
});
