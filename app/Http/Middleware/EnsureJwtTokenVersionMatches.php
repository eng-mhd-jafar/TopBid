<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use Symfony\Component\HttpFoundation\Response;

class EnsureJwtTokenVersionMatches
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // 1. محاولة استخراج التوكن وقراءة البيانات منه
            $payload = JWTAuth::parseToken()->getPayload();
            $user = $request->user(); // أو $request->user() إذا كان الـ guard الافتراضي api

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            // 2. التحقق من إصدار التوكن
            $payloadTokenVersion = (int) $payload->get('token_version', 0);

            if ($payloadTokenVersion !== (int) $user->jwt_token_version) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session expired. Please login again.',
                ], 401);
            }

        } catch (JWTException $e) {
            // في حال عدم وجود توكن أو كونه غير صالحة
            return response()->json([
                'success' => false,
                'message' => 'A token is required or invalid.',
            ], 401);
        }

        return $next($request);
    }
}