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
use App\Services\Classification\ClassifierInterface;
use App\Services\Clock\ClockInterface;
use App\Services\Ids\IdGeneratorInterface;
use App\Validation\TicketValidator;
use Carbon\CarbonImmutable;

class TicketService
{
    public function __construct(
        private readonly TicketRepository  $repository,
        private readonly TicketValidator   $validator,
        private readonly ClockInterface    $clock,
        private readonly IdGeneratorInterface $ids,
        private readonly ClassifierInterface  $classifier,
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
        $this->validator->validateCreate($input);

        $now    = $this->clock->now();
        $ticket = new Ticket(
            id:                       $this->ids->generate(),
            customerId:               $input['customer_id'],
            customerEmail:            $input['customer_email'],
            customerName:             $input['customer_name'],
            subject:                  $input['subject'],
            description:              $input['description'],
            category:                 Category::from($input['category']),
            priority:                 Priority::from($input['priority']),
            status:                   Status::from($input['status'] ?? 'new'),
            assignedTo:               $input['assigned_to'] ?? null,
            tags:                     $input['tags'] ?? [],
            metadata:                 $this->buildMetadata($input['metadata'] ?? []),
            classificationConfidence: null,
            classificationReasoning:  null,
            classificationKeywords:   null,
            createdAt:                CarbonImmutable::parse($input['created_at'] ?? $now->toIso8601String()),
            updatedAt:                CarbonImmutable::parse($input['updated_at'] ?? $now->toIso8601String()),
            resolvedAt:               isset($input['resolved_at']) ? CarbonImmutable::parse($input['resolved_at']) : null,
        );

        if ($autoClassify) {
            $result = $this->classifier->classify($ticket);
            $ticket = new Ticket(
                id:                       $ticket->id,
                customerId:               $ticket->customerId,
                customerEmail:            $ticket->customerEmail,
                customerName:             $ticket->customerName,
                subject:                  $ticket->subject,
                description:              $ticket->description,
                category:                 $ticket->category,
                priority:                 $ticket->priority,
                status:                   $ticket->status,
                assignedTo:               $ticket->assignedTo,
                tags:                     $ticket->tags,
                metadata:                 $ticket->metadata,
                classificationConfidence: $result->confidence,
                classificationReasoning:  $result->reasoning,
                classificationKeywords:   $result->keywords,
                createdAt:                $ticket->createdAt,
                updatedAt:                $ticket->updatedAt,
                resolvedAt:               $ticket->resolvedAt,
            );
        }

        $this->repository->insert($ticket);

        return $ticket;
    }

    public function update(Ticket $ticket, array $input): Ticket
    {
        $this->validator->validateUpdate($input);

        $now    = $this->clock->now();
        $ticket = new Ticket(
            id:                       $ticket->id,
            customerId:               $input['customer_id']    ?? $ticket->customerId,
            customerEmail:            $input['customer_email'] ?? $ticket->customerEmail,
            customerName:             $input['customer_name']  ?? $ticket->customerName,
            subject:                  $input['subject']        ?? $ticket->subject,
            description:              $input['description']    ?? $ticket->description,
            category:                 isset($input['category'])  ? Category::from($input['category'])  : $ticket->category,
            priority:                 isset($input['priority'])  ? Priority::from($input['priority'])  : $ticket->priority,
            status:                   isset($input['status'])    ? Status::from($input['status'])      : $ticket->status,
            assignedTo:               array_key_exists('assigned_to', $input) ? $input['assigned_to'] : $ticket->assignedTo,
            tags:                     $input['tags']             ?? $ticket->tags,
            metadata:                 isset($input['metadata'])  ? $this->buildMetadata($input['metadata']) : $ticket->metadata,
            classificationConfidence: $ticket->classificationConfidence,
            classificationReasoning:  $ticket->classificationReasoning,
            classificationKeywords:   $ticket->classificationKeywords,
            createdAt:                $ticket->createdAt,
            updatedAt:                CarbonImmutable::parse($input['updated_at'] ?? $now->toIso8601String()),
            resolvedAt:               isset($input['resolved_at']) ? CarbonImmutable::parse($input['resolved_at']) : $ticket->resolvedAt,
        );

        $this->repository->update($ticket);

        return $ticket;
    }

    public function delete(Ticket $ticket): void
    {
        $this->repository->delete($ticket);
    }

    private function buildMetadata(array $meta): TicketMetadata
    {
        return new TicketMetadata(
            source:     isset($meta['source'])      ? Source::tryFrom($meta['source'])           : null,
            browser:    $meta['browser']            ?? null,
            deviceType: isset($meta['device_type']) ? DeviceType::tryFrom($meta['device_type'])  : null,
        );
    }
}
