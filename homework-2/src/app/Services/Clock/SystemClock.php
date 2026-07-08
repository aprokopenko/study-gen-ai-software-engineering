<?php

declare(strict_types=1);

namespace App\Services\Clock;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class SystemClock implements ClockInterface
{
    public function now(): CarbonInterface
    {
        return CarbonImmutable::now('UTC');
    }
}
