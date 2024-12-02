<?php

namespace App\Exceptions;


use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use App\Exceptions\NotFoundException;
use App\Exceptions\BadRequestException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\ForbiddenException;

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
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Handle the rendering of the exception.
     */
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
            ], 404);
        }

        // Optionally handle other custom exceptions or general cases
        if ($exception instanceof UnauthorizedException) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 403);
        }

         // Optionally handle other custom exceptions or general cases
         if ($exception instanceof ForbiddenException) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 401);
        }

         // Optionally handle other custom exceptions or general cases
         if ($exception instanceof HttpException) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], $exception->getStatusCode());
        }

        return parent::render($request, $exception);
    }
}
