<?php

declare(strict_types=1);

namespace App\Services\Ids;

interface IdGeneratorInterface
{
    public function generate(): string;
}
