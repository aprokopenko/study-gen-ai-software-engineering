<?php

declare(strict_types=1);

namespace App\Http;

use App\Validation\ValidationException;
use Slim\Exception\HttpNotFoundException;
use Slim\Interfaces\ErrorRendererInterface;
use Throwable;

class ErrorRenderer implements ErrorRendererInterface
{
    public function __invoke(Throwable $exception, bool $displayErrorDetails): string
    {
        if ($exception instanceof ValidationException) {
            return (string) json_encode([
                'error'   => 'Validation failed',
                'details' => $exception->getErrors(),
            ]);
        }

        if ($exception instanceof HttpNotFoundException) {
            return (string) json_encode(['error' => 'Not found']);
        }

        return (string) json_encode(['error' => 'Internal server error']);
    }
}
