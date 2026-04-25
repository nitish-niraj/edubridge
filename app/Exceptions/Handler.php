<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
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
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $exception): void {
            if (app()->bound('sentry')) {
                app('sentry')->captureException($exception);
            }
        });

        $this->renderable(function (NotFoundHttpException $exception, Request $request) {
            return $this->renderHttpError($request, 404, 'errors.404', 'Resource not found.');
        });

        $this->renderable(function (TokenMismatchException $exception, Request $request) {
            return $this->renderHttpError($request, 419, 'errors.419', 'Session expired.');
        });

        $this->renderable(function (TooManyRequestsHttpException $exception, Request $request) {
            return $this->renderHttpError($request, 429, 'errors.429', 'Too many requests.');
        });

        $this->renderable(function (Throwable $exception, Request $request) {
            if ($exception instanceof \Illuminate\Validation\ValidationException || $exception instanceof \Illuminate\Auth\AuthenticationException || $exception instanceof \Illuminate\Http\Exceptions\HttpResponseException) {
                return null;
            }

            if ($this->isHttpException($exception)) {
                return null;
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 500,
                    'message' => 'Server error: ' . get_class($exception),
                ], 500);
            }

            return null;
        });
    }

    private function renderHttpError(Request $request, int $status, string $view, string $message)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => $status,
                'message' => $message,
            ], $status);
        }

        return response()->view($view, ['status' => $status], $status);
    }
}
