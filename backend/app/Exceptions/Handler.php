<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<Throwable>, LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        HttpResponseException::class,
        HttpExceptionInterface::class,
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * Normalizes HTTP exceptions (401 already handled, validation handled via
     * invalidJson, and all other HTTP statuses) to the project API envelope.
     */
    public function register(): void
    {
        $this->renderable(function (PaymentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], $e->responseStatus());
        });

        $this->renderable(function (PaymentGatewayException $e) {
            return response()->json([
                'success' => false,
                'message' => 'The payment gateway is unavailable. Please try again later.',
                'data' => null,
            ], 502);
        });

        $this->renderable(function (Throwable $e, Request $request) {
            if ($request->expectsJson()
                && ($e instanceof HttpExceptionInterface || $e instanceof HttpResponseException)) {
                return $this->buildApiError($e);
            }
        });
    }

    /**
     * Convert an invalid JSON validation response to the project envelope.
     */
    protected function invalidJson($request, ValidationException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'The given data was invalid.',
            'data' => $exception->errors(),
        ], $exception->status);
    }

    /**
     * Convert an authentication failure into the API envelope.
     *
     * This is a pure API application, so authentication failures are always
     * returned as a JSON 401 envelope, regardless of the Accept header.
     */
    protected function unauthenticated($request, AuthenticationException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated.',
            'data' => null,
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Build a consistent error envelope for an HTTP exception.
     */
    protected function buildApiError(Throwable $e): JsonResponse
    {
        if ($e instanceof HttpResponseException && $e->getResponse() instanceof JsonResponse) {
            return $e->getResponse();
        }

        $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
        $message = ($e instanceof HttpExceptionInterface && $e->getMessage())
            ? $e->getMessage()
            : 'Something went wrong.';

        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
        ], $status);
    }
}
