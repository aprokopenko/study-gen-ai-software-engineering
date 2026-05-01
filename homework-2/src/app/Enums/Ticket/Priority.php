<?php

declare(strict_types=1);

namespace App\Enums\Ticket;

enum Priority: string
{
    case Urgent = 'urgent';
    case High   = 'high';
    case Medium = 'medium';
    case Low    = 'low';
}
