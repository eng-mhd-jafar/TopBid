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
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class JwtAuthController extends Controller
{
    public function __construct(protected JwtAuthService $jwtAuthService) {}

    /**
     * حمولة الرموز الموحّدة التي ترجعها كل مسارات إصدار الجلسة.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function tokenPayload(array $result): array
    {
        return [
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'token_type' => $result['token_type'],
            'expires_in' => $result['expires_in'],
            'refresh_expires_in' => $result['refresh_expires_in'],
            'user' => (new RegisterResource($result['user']))->resolve(),
        ];
    }

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

        $responseData = $this->tokenPayload($result);

        return ApiResponse::successWithData($responseData, 'Login successfully');
    }

    public function verifyOtp(UserCheckCodeRequest $request)
    {
        $result = $this->jwtAuthService->verifyOtp($request->validated());

        if (! $result) {
            return ApiResponse::error('Invalid or expired OTP.', 422);
        }

        $responseData = $this->tokenPayload($result);

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

        $responseData = $this->tokenPayload($result);

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

    public function redirectToGoogle()
    {
        $url = Socialite::driver('google')->stateless()->redirect()->getTargetUrl();

        return ApiResponse::successWithData(['url' => $url], 'Google OAuth URL generated.');
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (Throwable $e) {
            Log::error('Google callback failed: '.$e->getMessage());

            return ApiResponse::unauthorized('Google authentication failed.');
        }

        if (! $googleUser->getEmail()) {
            return ApiResponse::unauthorized('Google account has no usable email address.');
        }

        $result = $this->jwtAuthService->loginWithGoogle($googleUser);

        return ApiResponse::successWithData(
            $this->tokenPayload($result),
            'Login with Google successful.'
        );
    }
}
