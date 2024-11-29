<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Response;

class ForbiddenException extends Exception
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
            'message' => $this->getMessage() ?: 'Forbidden',
            'errors' => $this->getCode() ? $this->getCode() : []
        ];

        return response()->json($response, Response::HTTP_FORBIDDEN);
    }
}
