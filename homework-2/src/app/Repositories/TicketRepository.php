<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Entities\Ticket;
use App\Filters\TicketFilter;
use App\Services\Database;

class TicketRepository
{
    private const TABLE = 'tickets';

    public function __construct(private readonly Database $db) {}

    public function findAll(TicketFilter $filter): array
    {
        $where = $this->buildWhere($filter);
        $where['LIMIT'] = [$filter->offset, $filter->limit];

        if ($filter->sort !== null) {
            $where['ORDER'] = $this->resolveOrder($filter->sort);
        } else {
            $where['ORDER'] = ['created_at' => 'DESC'];
        }

        $rows = $this->db->query()->select(self::TABLE, '*', $where) ?: [];

        return array_map(Ticket::fromRow(...), $rows);
    }

    public function findById(string $id): ?Ticket
    {
        $row = $this->db->query()->get(self::TABLE, '*', ['id' => $id]);

        return $row ? Ticket::fromRow($row) : null;
    }

    public function insert(Ticket $ticket): void
    {
        $this->db->query()->insert(self::TABLE, $ticket->toRow());
    }

    public function update(Ticket $ticket): void
    {
        $this->db->query()->update(self::TABLE, $ticket->toRow(), ['id' => $ticket->id]);
    }

    public function delete(Ticket $ticket): void
    {
        $this->db->query()->delete(self::TABLE, ['id' => $ticket->id]);
    }

    public function insertBatch(array $rows): void
    {
        if (empty($rows)) {
            return;
        }
        $this->db->query()->insert(self::TABLE, $rows);
    }

    private function buildWhere(TicketFilter $filter): array
    {
        $where = [];

        if ($filter->category !== null)   $where['category']    = $filter->category;
        if ($filter->priority !== null)   $where['priority']    = $filter->priority;
        if ($filter->status !== null)     $where['status']      = $filter->status;
        if ($filter->customerId !== null) $where['customer_id'] = $filter->customerId;
        if ($filter->assignedTo !== null) $where['assigned_to'] = $filter->assignedTo;

        if ($filter->q !== null) {
            $where['OR'] = [
                'subject[~]'     => $filter->q,
                'description[~]' => $filter->q,
            ];
        }

        return $where;
    }

    private function resolveOrder(string $sort): array
    {
        $dir = 'ASC';
        if (str_starts_with($sort, '-')) {
            $dir  = 'DESC';
            $sort = ltrim($sort, '-');
        }

        $allowed = ['created_at', 'updated_at', 'priority', 'status', 'category'];

        return in_array($sort, $allowed, true) ? [$sort => $dir] : ['created_at' => 'DESC'];
    }
}
