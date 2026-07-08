<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\Clock\ClockInterface;
use App\Services\Database;

class TicketLogRepository
{
    private const TABLE = 'ticket_logs';

    public function __construct(
        private readonly Database     $db,
        private readonly ClockInterface $clock,
    ) {}

    public function insert(array $row): void
    {
        $row['created_at'] ??= $this->clock->now()->toIso8601String();
        $this->db->query()->insert(self::TABLE, $row);
    }
}