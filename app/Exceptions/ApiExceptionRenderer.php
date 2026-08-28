<?php

namespace App\Exceptions;

use App\Http\Helpers\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * يحوّل كل استثناء يخرج من الـ API إلى الشكل الموحّد:
 *
 *   { "success": false, "message": "...", "errors": { "field": ["..."] } }
 *
 * قبل هذا الصنف كانت الأخطاء تخرج بثلاثة أشكال مختلفة، فيضطر العميل
 * إلى ثلاثة مسارات قراءة لنفس الغرض.
 */
class ApiExceptionRenderer
{
    public static function shouldHandle(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    public static function render(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => ApiResponse::error(
                'The given data was invalid.',
                $e->status,
                $e->errors()
            ),

            $e instanceof AuthenticationException => ApiResponse::unauthorized('Unauthenticated.'),

            $e instanceof AuthorizationException,
            $e instanceof AccessDeniedHttpException => ApiResponse::forbidden(
                'This action is unauthorized.'
            ),

            $e instanceof ModelNotFoundException => ApiResponse::notFound('Resource not found.'),

            $e instanceof NotFoundHttpException => ApiResponse::notFound('Endpoint not found.'),

            $e instanceof MethodNotAllowedHttpException => ApiResponse::error(
                'Method not allowed for this endpoint.',
                405
            ),

            $e instanceof TooManyRequestsHttpException => self::tooManyRequests($e),

            $e instanceof HttpExceptionInterface => ApiResponse::error(
                $e->getMessage() !== '' ? $e->getMessage() : 'Request failed.',
                $e->getStatusCode()
            ),

            default => self::serverError($e),
        };
    }

    private static function tooManyRequests(TooManyRequestsHttpException $e): JsonResponse
    {
        $retryAfter = $e->getHeaders()['Retry-After'] ?? null;

        return ApiResponse::error(
            'Too many requests. Please try again later.',
            429,
            $retryAfter !== null ? ['retry_after' => [(int) $retryAfter]] : []
        );
    }

    /**
     * في بيئة التطوير تُكشف تفاصيل الخطأ، وفي الإنتاج تُخفى.
     * إخفاؤها في التطوير أيضاً يجعل تتبّع الأعطال شبه مستحيل.
     */
    private static function serverError(Throwable $e): JsonResponse
    {
        if (! config('app.debug')) {
            return ApiResponse::error('Server error. Please try again later.', 500);
        }

        return ApiResponse::error($e->getMessage(), 500, [
            'exception' => [$e::class],
            'location' => [$e->getFile().':'.$e->getLine()],
        ]);
    }
}
