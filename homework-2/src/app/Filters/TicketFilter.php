<?php

declare(strict_types=1);

namespace App\Filters;

class TicketFilter
{
    public readonly int $limit;
    public readonly int $offset;

    public function __construct(
        public readonly ?string $category   = null,
        public readonly ?string $priority   = null,
        public readonly ?string $status     = null,
        public readonly ?string $customerId = null,
        public readonly ?string $assignedTo = null,
        public readonly ?string $q          = null,
        int                     $limit      = 50,
        int                     $offset     = 0,
        public readonly ?string $sort       = null,
    ) {
        $this->limit  = min(max(1, $limit), 200);
        $this->offset = max(0, $offset);
    }

    public static function fromParams(array $params): self
    {
        return new self(
            category:   $params['category']    ?? null,
            priority:   $params['priority']    ?? null,
            status:     $params['status']      ?? null,
            customerId: $params['customer_id'] ?? null,
            assignedTo: $params['assigned_to'] ?? null,
            q:          $params['q']           ?? null,
            limit:      isset($params['limit'])  ? (int) $params['limit']  : 50,
            offset:     isset($params['offset']) ? (int) $params['offset'] : 0,
            sort:       $params['sort']        ?? null,
        );
    }
}
