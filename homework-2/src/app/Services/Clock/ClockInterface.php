<?php

declare(strict_types=1);

namespace App\Services\Clock;

use Carbon\CarbonInterface;

interface ClockInterface
{
    public function now(): CarbonInterface;
}
