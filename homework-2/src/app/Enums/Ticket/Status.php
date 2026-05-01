<?php

declare(strict_types=1);

namespace App\Enums\Ticket;

enum Status: string
{
    case New             = 'new';
    case InProgress      = 'in_progress';
    case WaitingCustomer = 'waiting_customer';
    case Resolved        = 'resolved';
    case Closed          = 'closed';
}
