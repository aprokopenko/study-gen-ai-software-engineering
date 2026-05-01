<?php

declare(strict_types=1);

namespace App\Services\Ids;

use Ramsey\Uuid\Uuid;

class UuidGenerator implements IdGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::uuid4()->toString();
    }
}
