<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Response;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

class BadRequestException extends Exception
{
    /**
     * Render the exception into an HTTP response.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function render($request)
    {
        $response = [
            'status' => false,
            'message' => $this->getMessage() ?: 'Bad Request',
            'errors' => $this->getCode() ? $this->getCode() : []
        ];

        return response()->json($response, Response::HTTP_BAD_REQUEST);
    }

    /**
     * Customize the exception when validation fails.
     *
     * @param Validator $validator
     * @return \Illuminate\Http\Response
     */
    public static function handleValidationException(Validator $validator)
    {
        $errors = $validator->errors()->getMessages();
        return response()->json([
            'status' => false,
            'message' => 'Bad Request',
            'errors' => $errors
        ], Response::HTTP_BAD_REQUEST);
    }
}
