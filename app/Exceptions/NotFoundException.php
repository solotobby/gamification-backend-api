<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Response;

class NotFoundException extends Exception
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
            'message' => $this->getMessage() ?: 'Resource Not Found',
            'errors' => $this->getCode() ? $this->getCode() : []
        ];

        return response()->json($response, Response::HTTP_NOT_FOUND);
    }
}
