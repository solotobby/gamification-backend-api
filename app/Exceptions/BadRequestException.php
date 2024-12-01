<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Response;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

class BadRequestException extends Exception
{
    public function __construct($message = 'Bad Request', $code = 400)
    {
        parent::__construct($message, $code);
    }
}
