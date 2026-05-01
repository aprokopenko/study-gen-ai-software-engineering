<?php

declare(strict_types=1);

namespace App\Validation;

use RuntimeException;

class ValidationException extends RuntimeException
{
    /** @param array<string,string> $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Validation failed');
    }

    /** @return array<string,string> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
