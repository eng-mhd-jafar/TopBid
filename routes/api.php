<?php

use App\Http\Controllers\Api\Admin\AuctionModerationController;
use App\Http\Controllers\Api\User\ProfileController;
use App\Http\Controllers\Api\User\NotificationController;
use App\Http\Controllers\Api\User\BidController;
use App\Http\Controllers\Api\User\DeviceController;
use App\Http\Controllers\Api\User\JwtAuthController;
use App\Http\Controllers\Api\User\AuctionController;
use App\Http\Controllers\Api\User\CategoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->controller(JwtAuthController::class)->group(function () {

    Route::post('register', [JwtAuthController::class, 'register']);
    Route::post('verify-otp', [JwtAuthController::class, 'verifyOtp'])->middleware('throttle:otp-limiter');
    Route::post('resend-otp', [JwtAuthController::class, 'resendOtp'])->middleware('throttle:otp-limiter');
    Route::post('login', [JwtAuthController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('forgot-password', [JwtAuthController::class, 'forgotPassword'])->middleware('throttle:auth-forgot-password');
    Route::post('reset-password', [JwtAuthController::class, 'resetPassword'])->middleware('throttle:auth-reset-password');
    Route::middleware(['auth:jwt', 'jwt.token.version'])->group(function () {
        Route::delete('logout', [JwtAuthController::class, 'logout']);
    });

    Route::post('refresh', [JwtAuthController::class, 'refresh']);

    Route::get('google', [JwtAuthController::class, 'redirectToGoogle']);
    Route::get('google/callback', [JwtAuthController::class, 'handleGoogleCallback']);
});

// bid routes
Route::middleware(['auth:jwt', 'jwt.token.version'])->group(function () {
    Route::get('/bids', [BidController::class, 'index']);
    Route::post('/bids', [BidController::class, 'store']);
});


// auction routes
Route::group(['middleware' => ['auth:jwt', 'jwt.token.version']], function () {
    Route::post('/auctions', [AuctionController::class, 'store']);
    Route::get('/auctions/{id}', [AuctionController::class, 'show']);
    Route::get('my-auctions', [AuctionController::class, 'getMyAuctions']);
});
Route::get('/auctions/category/{category_id}', [AuctionController::class, 'getAuctionsByCategory']);
Route::get('/auctions', [AuctionController::class, 'index']);


// category routes
Route::get('/categories', [CategoryController::class, 'index']);
Route::middleware(['auth:jwt', 'jwt.token.version', 'admin'])->group(function () {
    Route::post('/categories', [CategoryController::class, 'store']);
});


// admin auction moderation routes
Route::prefix('admin/auctions')->middleware(['auth:jwt', 'jwt.token.version', 'admin'])->group(function () {
    Route::post('{id}/approve', [AuctionModerationController::class, 'approve']);
    Route::post('{id}/reject', [AuctionModerationController::class, 'reject']);
});


// device token routes
Route::middleware(['auth:jwt', 'jwt.token.version'])->group(function () {
    Route::post('/devices', [DeviceController::class, 'saveToken']);
});


// notifications routes
Route::middleware(['auth:jwt', 'jwt.token.version'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
});


// profile routes
Route::group(['middleware' => ['auth:jwt', 'jwt.token.version'], 'prefix' => 'me'], function () {
    Route::get('/', [ProfileController::class, 'show']);
    Route::put('/', [ProfileController::class, 'update']);
    Route::put('password', [ProfileController::class, 'changePassword']);
});
