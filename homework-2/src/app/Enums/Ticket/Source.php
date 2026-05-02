<?php

declare(strict_types=1);

namespace App\Enums\Ticket;

enum Source: string
{
    case WebForm = 'web_form';
    case Email = 'email';
    case Api = 'api';
    case Chat = 'chat';
    case Phone = 'phone';
}
