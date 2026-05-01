<?php

declare(strict_types=1);

namespace App\Enums\Ticket;

enum DeviceType: string
{
    case Desktop = 'desktop';
    case Mobile  = 'mobile';
    case Tablet  = 'tablet';
    case Other   = 'other';
}
