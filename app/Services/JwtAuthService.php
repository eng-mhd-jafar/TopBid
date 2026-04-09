<?php

namespace App\Services;

use App\Exceptions\RegistrationFailedException;
use App\Jobs\SendOtpJob;
use App\Jobs\SendPasswordChangedEmailJob;
use App\Jobs\SendPasswordResetTokenJob;
use App\Models\User;
use App\Models\UserRefreshToken;
use App\Repositories\JwtAuthRepository;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class JwtAuthService
{
    public function __construct(protected JwtAuthRepository $jwtAuthRepository)
    {
    }

    protected function issueTokensForUser(User $user): array
    {
        $accessToken = JWTAuth::fromUser($user);
        $refreshTtlSeconds = (int) config('jwt.refresh_ttl', 20160) * 60;
        $refreshToken = Str::random(80);
        $tokenHash = hash('sha256', $refreshToken);

        UserRefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => $tokenHash,
            'expires_at' => now()->addSeconds($refreshTtlSeconds),
            'revoked_at' => null,
            'jwt_token_version' => (int) $user->jwt_token_version,
            'last_used_at' => null,
        ]);

        $factory = JWTAuth::factory();

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'bearer',
            'expires_in' => $factory->getTTL() * 60,
            'refresh_expires_in' => $refreshTtlSeconds,
            'user' => $user,
        ];
    }

    public function register(array $data): array
    {
        try {
            DB::beginTransaction();

            if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
                $data['avatar'] = $data['avatar']->store('avatars', 'public');
            }

            $data['OTP'] = rand(1000, 9999);
            $data['verification_code_expires_at'] = now()->addMinutes(5);
            $data['last_otp_at'] = now();
            $data['failed_attempts'] = 0;
            $user = $this->jwtAuthRepository->create($data);
            DB::commit();
            SendOtpJob::dispatch($user, (string) $data['OTP']);

            return ['user' => $user];
        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($data['avatar']) && is_string($data['avatar'])) {
                Storage::disk('public')->delete($data['avatar']);
            }
            Log::error('Registration failed: ' . $e->getMessage());
            throw new RegistrationFailedException;
        }
    }

    public function login(array $credentials): ?array
    {
        $user = $this->jwtAuthRepository->findUserByEmail($credentials['email']);
        if (!$user || !$user->email_verified_at) {
            return null;
        }

        $token = JWTAuth::attempt($credentials);

        if (!$token) {
            return null;
        }

        $user = JWTAuth::user();

        return $this->issueTokensForUser($user);
    }

    public function verifyOtp(array $data): ?array
    {
        $user = $this->jwtAuthRepository->findUserByEmail($data['email']);
        if (!$user) {
            return null;
        }

        if ($user->failed_attempts >= 3) {
            return null;
        }

        if ($user->verification_code_expires_at && now()->greaterThan($user->verification_code_expires_at)) {
            return null;
        }

        if ((string) $user->OTP !== (string) $data['OTP']) {
            $user->update(['failed_attempts' => $user->failed_attempts + 1]);

            return null;
        }

        $user->update([
            'failed_attempts' => 0,
            'email_verified_at' => now(),
            'OTP' => null,
            'verification_code_expires_at' => null,
        ]);

        return $this->issueTokensForUser($user);
    }

    public function resendOtp(string $email): void
    {
        $user = $this->jwtAuthRepository->findUserByEmail($email);
        if (!$user) {
            return;
        }

        if ($user->email_verified_at) {
            return;
        }

        if ($user->last_otp_at && now()->lt($user->last_otp_at->copy()->addSeconds(60))) {
            return;
        }

        $otp = rand(1000, 9999);
        $user->update([
            'OTP' => $otp,
            'verification_code_expires_at' => now()->addMinutes(5),
            'last_otp_at' => now(),
            'failed_attempts' => 0,
        ]);

        SendOtpJob::dispatch($user, (string) $otp);
    }

    public function getCurrentUser(): ?User
    {
        return JWTAuth::user();
    }

    public function logout(User $user): void
    {
        $currentAccessToken = JWTAuth::getToken();
        if ($currentAccessToken) {
            JWTAuth::invalidate($currentAccessToken);
        }

        UserRefreshToken::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function refreshTokenByRefreshToken(string $refreshToken): ?array
    {
        $tokenHash = hash('sha256', $refreshToken);

        $refreshRecord = UserRefreshToken::query()
            ->where('token_hash', $tokenHash)
            ->whereNull('revoked_at')
            ->first();

        if (!$refreshRecord) {
            return null;
        }

        if (now()->greaterThan($refreshRecord->expires_at)) {
            return null;
        }

        $user = $refreshRecord->user;

        if (!$user || (int) $refreshRecord->jwt_token_version !== (int) $user->jwt_token_version) {
            return null;
        }

        $refreshRecord->update([
            'revoked_at' => now(),
            'last_used_at' => now(),
        ]);

        return $this->issueTokensForUser($user);
    }

    public function sendPasswordResetLink(string $email): void
    {
        $user = $this->jwtAuthRepository->findUserByEmail($email);

        // Always return success response in controller to avoid account enumeration.
        if (!$user) {
            return;
        }

        $rateLimitKey = "password-reset-requested:{$user->id}";
        if (!Cache::add($rateLimitKey, true, now()->addSeconds(60))) {
            return;
        }

        /** @var PasswordBroker $broker */
        $broker = Password::broker();
        $token = $broker->createToken($user);
        SendPasswordResetTokenJob::dispatch($user, $token);
    }

    public function resetPassword(array $data): bool
    {
        $status = Password::reset(
            [
                'email' => $data['email'],
                'password' => $data['password'],
                'password_confirmation' => $data['password_confirmation'],
                'token' => $data['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    'jwt_token_version' => (int) $user->jwt_token_version + 1,
                ])->save();

                SendPasswordChangedEmailJob::dispatch($user);
            }
        );

        return $status === Password::PASSWORD_RESET;
    }
}
