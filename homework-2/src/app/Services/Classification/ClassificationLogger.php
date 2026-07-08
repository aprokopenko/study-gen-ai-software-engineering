<?php

declare(strict_types=1);

namespace App\Services\Classification;

use App\Repositories\TicketLogRepository;
use App\Services\Ids\IdGeneratorInterface;

class ClassificationLogger
{
    public function __construct(
        private readonly TicketLogRepository  $repository,
        private readonly IdGeneratorInterface $ids,
    ) {}

    public function log(string $ticketId, ClassificationResult $result): void
    {
        $this->repository->insert([
            'id' => $this->ids->generate(),
            'ticket_id' => $ticketId,
            'event' => 'classify',
            'payload' => json_encode([
                'confidence' => $result->confidence,
                'reasoning' => $result->reasoning,
                'keywords' => $result->keywords,
                'suggested_category' => $result->suggestedCategory->value,
                'suggested_priority' => $result->suggestedPriority->value,
            ], JSON_THROW_ON_ERROR),
        ]);
    }
}
