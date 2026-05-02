<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\Ticket;
use App\Entities\TicketMetadata;
use App\Enums\Ticket\Category;
use App\Enums\Ticket\DeviceType;
use App\Enums\Ticket\Priority;
use App\Enums\Ticket\Source;
use App\Enums\Ticket\Status;
use App\Filters\TicketFilter;
use App\Repositories\TicketRepository;
use App\Services\Classification\ClassificationLogger;
use App\Services\Classification\ClassificationResult;
use App\Services\Classification\ClassifierInterface;
use App\Services\Clock\ClockInterface;
use App\Services\Ids\IdGeneratorInterface;
use App\Validation\TicketValidator;
use Carbon\CarbonImmutable;

class TicketService
{
    public function __construct(
        private readonly TicketRepository $repository,
        private readonly TicketValidator $validator,
        private readonly ClockInterface $clock,
        private readonly IdGeneratorInterface $ids,
        private readonly ClassifierInterface $classifier,
        private readonly ClassificationLogger $logger,
    ) {}

    /** @return Ticket[] */
    public function list(TicketFilter $filter): array
    {
        return $this->repository->findAll($filter);
    }

    public function findOrFail(string $id): Ticket
    {
        $ticket = $this->repository->findById($id);
        if ($ticket === null) {
            throw new TicketNotFoundException("Ticket not found: {$id}");
        }
        return $ticket;
    }

    public function create(array $input, bool $autoClassify = false): Ticket
    {
        $categoryProvided = !empty($input['category']);
        $priorityProvided = !empty($input['priority']);

        $input['category'] ??= Category::Other->value;
        $input['priority'] ??= Priority::Medium->value;

        $this->validator->validateCreate($input);

        $now = $this->clock->now();
        $ticket = new Ticket(
            id: $this->ids->generate(),
            customerId: $input['customer_id'],
            customerEmail: $input['customer_email'],
            customerName: $input['customer_name'],
            subject: $input['subject'],
            description: $input['description'],
            category: Category::from($input['category']),
            priority: Priority::from($input['priority']),
            status: Status::from($input['status'] ?? 'new'),
            assignedTo: $input['assigned_to'] ?? null,
            tags: $input['tags'] ?? [],
            metadata: $this->buildMetadata($input['metadata'] ?? []),
            classificationConfidence: null,
            classificationReasoning: null,
            classificationKeywords: null,
            createdAt: CarbonImmutable::parse($input['created_at'] ?? $now->toIso8601String()),
            updatedAt: CarbonImmutable::parse($input['updated_at'] ?? $now->toIso8601String()),
            resolvedAt: isset($input['resolved_at']) ? CarbonImmutable::parse($input['resolved_at']) : null,
        );

        if ($autoClassify) {
            $result = $this->classifier->classify($ticket);
            $ticket = $ticket->with([
                ...($categoryProvided ? [] : ['category' => $result->suggestedCategory]),
                ...($priorityProvided ? [] : ['priority' => $result->suggestedPriority]),
                'classificationConfidence' => $result->confidence,
                'classificationReasoning' => $result->reasoning,
                'classificationKeywords' => $result->keywords,
            ]);
        }

        $this->repository->insert($ticket);

        if ($autoClassify) {
            $this->logger->log($ticket->id, $result);
        }

        return $ticket;
    }

    public function update(Ticket $ticket, array $input): Ticket
    {
        $this->validator->validateUpdate($input);

        $fieldMap = [
            'customer_id' => fn($v) => ['customerId', $v],
            'customer_email' => fn($v) => ['customerEmail', $v],
            'customer_name' => fn($v) => ['customerName', $v],
            'subject' => fn($v) => ['subject', $v],
            'description' => fn($v) => ['description', $v],
            'tags' => fn($v) => ['tags', $v],
            'category' => fn($v) => ['category', Category::from($v)],
            'priority' => fn($v) => ['priority', Priority::from($v)],
            'status' => fn($v) => ['status', Status::from($v)],
            'metadata' => fn($v) => ['metadata', $this->buildMetadata($v)],
            'resolved_at' => fn($v) => ['resolvedAt', CarbonImmutable::parse($v)],
        ];

        $overrides = ['updatedAt' => CarbonImmutable::parse($input['updated_at'] ?? $this->clock->now()->toIso8601String())];

        foreach ($fieldMap as $in => $resolve) {
            if (isset($input[$in])) {
                [$out, $value] = $resolve($input[$in]);
                $overrides[$out] = $value;
            }
        }

        // assigned_to can be explicitly set to null to unassign
        if (array_key_exists('assigned_to', $input)) {
            $overrides['assignedTo'] = $input['assigned_to'];
        }

        $ticket = $ticket->with($overrides);
        $this->repository->update($ticket);

        return $ticket;
    }

    public function autoClassify(Ticket $ticket): ClassificationResult
    {
        $result = $this->classifier->classify($ticket);

        $ticket = $ticket->with([
            'category' => $result->suggestedCategory,
            'priority' => $result->suggestedPriority,
            'classificationConfidence' => $result->confidence,
            'classificationReasoning' => $result->reasoning,
            'classificationKeywords' => $result->keywords,
            'updatedAt' => $this->clock->now(),
        ]);

        $this->repository->update($ticket);
        $this->logger->log($ticket->id, $result);

        return $result;
    }

    public function delete(Ticket $ticket): void
    {
        $this->repository->delete($ticket);
    }

    private function buildMetadata(array $meta): TicketMetadata
    {
        return new TicketMetadata(
            source: isset($meta['source']) ? Source::tryFrom($meta['source']) : null,
            browser: $meta['browser'] ?? null,
            deviceType: isset($meta['device_type']) ? DeviceType::tryFrom($meta['device_type']) : null,
        );
    }
}
