<?php

declare(strict_types=1);

namespace App\Http;

use App\Validation\ValidationException;
use Slim\Exception\HttpException;
use Slim\Handlers\ErrorHandler as SlimErrorHandler;

class ErrorHandler extends SlimErrorHandler
{
    protected function determineStatusCode(): int
    {
        if ($this->exception instanceof ValidationException) {
            return 400;
        }

        return parent::determineStatusCode();
    }
}
