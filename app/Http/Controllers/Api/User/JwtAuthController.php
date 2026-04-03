<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\JwtLoginRequest;
use App\Http\Requests\JwtRegisterRequest;
use App\Http\Requests\RefreshTokenRequest;
use App\Http\Requests\ResendOtpRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\UserCheckCodeRequest;
use App\Http\Resources\RegisterResource;
use App\Services\JwtAuthService;
use Illuminate\Http\Request;

class JwtAuthController extends Controller
{
    public function __construct(protected JwtAuthService $jwtAuthService) {}

    public function register(JwtRegisterRequest $request)
    {
        $result = $this->jwtAuthService->register($request->validated());

        return ApiResponse::success(
            'Registered successfully. Please verify OTP sent to your email.',
            201
        );
    }

    public function login(JwtLoginRequest $request)
    {
        $result = $this->jwtAuthService->login($request->only('email', 'password'));

        if (! $result) {
            return ApiResponse::unauthorized('Invalid credentials or account not verified.');
        }

        $responseData = [
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'token_type' => $result['token_type'],
            'expires_in' => $result['expires_in'],
            'refresh_expires_in' => $result['refresh_expires_in'],
            'user' => (new RegisterResource($result['user']))->resolve(),
        ];

        return ApiResponse::successWithData($responseData, 'Login successfully');
    }

    public function verifyOtp(UserCheckCodeRequest $request)
    {
        $result = $this->jwtAuthService->verifyOtp($request->validated());

        if (! $result) {
            return ApiResponse::error('Invalid or expired OTP.', 422);
        }

        $responseData = [
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'token_type' => $result['token_type'],
            'expires_in' => $result['expires_in'],
            'refresh_expires_in' => $result['refresh_expires_in'],
            'user' => (new RegisterResource($result['user']))->resolve(),
        ];

        return ApiResponse::successWithData($responseData, 'Email verified successfully.');
    }

    public function resendOtp(ResendOtpRequest $request)
    {
        $this->jwtAuthService->resendOtp($request->validated('email'));

        return ApiResponse::success('If your email exists, a new OTP has been sent.');
    }

    public function logout(Request $request)
    {
        $this->jwtAuthService->logout($request->user());

        return ApiResponse::success('Successfully logged out');
    }

    public function refresh(RefreshTokenRequest $request)
    {
        $result = $this->jwtAuthService->refreshTokenByRefreshToken(
            (string) $request->validated('refresh_token')
        );

        if (! $result) {
            return ApiResponse::unauthorized('Unable to refresh token');
        }

        $responseData = [
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'token_type' => $result['token_type'],
            'expires_in' => $result['expires_in'],
            'refresh_expires_in' => $result['refresh_expires_in'],
            'user' => (new RegisterResource($result['user']))->resolve(),
        ];

        return ApiResponse::successWithData($responseData, 'Token refreshed successfully');
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $this->jwtAuthService->sendPasswordResetLink($request->validated('email'));

        return ApiResponse::success('If your email exists, a password reset link has been sent.');
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $isReset = $this->jwtAuthService->resetPassword($request->validated());

        if (! $isReset) {
            return ApiResponse::error('Invalid reset data.', 422);
        }

        return ApiResponse::success('Password has been reset successfully.');
    }
}
