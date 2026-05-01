<?php

declare(strict_types=1);

namespace App\Entities;

use App\Enums\Ticket\Category;
use App\Enums\Ticket\Priority;
use App\Enums\Ticket\Status;
use Carbon\CarbonImmutable;

readonly class Ticket
{
    public function __construct(
        public string          $id,
        public string          $customerId,
        public string          $customerEmail,
        public string          $customerName,
        public string          $subject,
        public string          $description,
        public Category        $category,
        public Priority        $priority,
        public Status          $status,
        public ?string         $assignedTo,
        public array           $tags,
        public TicketMetadata  $metadata,
        public ?float          $classificationConfidence,
        public ?string         $classificationReasoning,
        public ?array          $classificationKeywords,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
        public ?CarbonImmutable $resolvedAt,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id:                       $row['id'],
            customerId:               $row['customer_id'],
            customerEmail:            $row['customer_email'],
            customerName:             $row['customer_name'],
            subject:                  $row['subject'],
            description:              $row['description'],
            category:                 Category::from($row['category']),
            priority:                 Priority::from($row['priority']),
            status:                   Status::from($row['status']),
            assignedTo:               $row['assigned_to'] ?? null,
            tags:                     json_decode($row['tags'] ?? '[]', true),
            metadata:                 TicketMetadata::fromRow($row),
            classificationConfidence: isset($row['classification_confidence']) ? (float) $row['classification_confidence'] : null,
            classificationReasoning:  $row['classification_reasoning'] ?? null,
            classificationKeywords:   isset($row['classification_keywords']) ? json_decode($row['classification_keywords'], true) : null,
            createdAt:                CarbonImmutable::parse($row['created_at']),
            updatedAt:                CarbonImmutable::parse($row['updated_at']),
            resolvedAt:               isset($row['resolved_at']) ? CarbonImmutable::parse($row['resolved_at']) : null,
        );
    }

    public function toRow(): array
    {
        return array_merge([
            'id'                        => $this->id,
            'customer_id'               => $this->customerId,
            'customer_email'            => $this->customerEmail,
            'customer_name'             => $this->customerName,
            'subject'                   => $this->subject,
            'description'               => $this->description,
            'category'                  => $this->category->value,
            'priority'                  => $this->priority->value,
            'status'                    => $this->status->value,
            'assigned_to'               => $this->assignedTo,
            'tags'                      => json_encode($this->tags),
            'classification_confidence' => $this->classificationConfidence,
            'classification_reasoning'  => $this->classificationReasoning,
            'classification_keywords'   => $this->classificationKeywords !== null ? json_encode($this->classificationKeywords) : null,
            'created_at'                => $this->createdAt->toIso8601String(),
            'updated_at'                => $this->updatedAt->toIso8601String(),
            'resolved_at'               => $this->resolvedAt?->toIso8601String(),
        ], $this->metadata->toRow());
    }

    public function jsonSerialize(): array
    {
        return [
            'id'             => $this->id,
            'customer_id'    => $this->customerId,
            'customer_email' => $this->customerEmail,
            'customer_name'  => $this->customerName,
            'subject'        => $this->subject,
            'description'    => $this->description,
            'category'       => $this->category->value,
            'priority'       => $this->priority->value,
            'status'         => $this->status->value,
            'assigned_to'    => $this->assignedTo,
            'tags'           => $this->tags,
            'metadata'       => [
                'source'      => $this->metadata->source?->value,
                'browser'     => $this->metadata->browser,
                'device_type' => $this->metadata->deviceType?->value,
            ],
            'classification' => [
                'confidence' => $this->classificationConfidence,
                'reasoning'  => $this->classificationReasoning,
                'keywords'   => $this->classificationKeywords,
            ],
            'created_at'  => $this->createdAt->toIso8601String(),
            'updated_at'  => $this->updatedAt->toIso8601String(),
            'resolved_at' => $this->resolvedAt?->toIso8601String(),
        ];
    }
}
