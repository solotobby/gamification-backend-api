<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Validation\ValidationException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $exception)
    {
        // Handle custom NotFoundException
        if ($exception instanceof NotFoundException) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 404);
        }

        // Handle custom BadRequestException
        if ($exception instanceof BadRequestException) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 400); // Adjusted to use 400 for bad request
        }

        // Handle custom UnauthorizedException (Unauthenticated)
    if ($exception instanceof UnauthorizedException) {
        return response()->json([
            'status' => false, // Add status field
            'message' => 'Unauthenticated.' // Custom message
        ], 401);
    }

        // Handle custom ForbiddenException
        if ($exception instanceof ForbiddenException) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 403); // Adjusted to use 403 for forbidden
        }

        // Handle ValidationException for failed validation
        if ($exception instanceof ValidationException) {
            // Flatten the validation errors and join them into a single string
            $errors = collect($exception->errors())
                ->flatten()
                ->implode('; ');

            return response()->json([
                'status' => false,
                'message' => $errors,
            ], 422); // HTTP status 422 for validation errors
        }


        // Handle HttpException (General HTTP Errors)
        if ($exception instanceof HttpException) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], $exception->getStatusCode());
        }

        // Default Laravel exception handler
        return parent::render($request, $exception);
    }
}
