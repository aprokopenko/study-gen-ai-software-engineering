<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Entities\Ticket;
use App\Enums\Ticket\Category;
use App\Enums\Ticket\Priority;
use App\Services\Classification\ClassificationResult;
use App\Services\Classification\ClassifierInterface;
use Tests\Concerns\AppTestCase;
use Tests\Traits\TicketDataBuilder;

class ClassificationTest extends AppTestCase
{
    use TicketDataBuilder;

    /**
     * Req 6.1 — classifier resolved from container implements interface
     */
    public function test_classifier_resolved_from_container_implements_interface(): void
    {
        $classifier = $this->app->getContainer()->get(ClassifierInterface::class);

        $this->assertInstanceOf(ClassifierInterface::class, $classifier);
    }

    /**
     * Req 6.2 — classify returns a ClassificationResult
     */
    public function test_classify_returns_valid_classification_result(): void
    {
        $classifier = $this->app->getContainer()->get(ClassifierInterface::class);
        $ticket = Ticket::fromRow($this->validTicketRow());

        $result = $classifier->classify($ticket);

        $this->assertInstanceOf(ClassificationResult::class, $result);
    }

    /**
     * Req 6.3 — confidence is between 0.0 and 1.0
     */
    public function test_classification_result_confidence_is_between_zero_and_one(): void
    {
        $classifier = $this->app->getContainer()->get(ClassifierInterface::class);
        $ticket = Ticket::fromRow($this->validTicketRow());

        $result = $classifier->classify($ticket);

        $this->assertTrue(
            $result->confidence >= 0.0 && $result->confidence <= 1.0,
            "Expected confidence between 0.0 and 1.0, got {$result->confidence}"
        );
    }

    /**
     * Req 6.4 — reasoning is a string
     */
    public function test_classification_result_reasoning_is_string(): void
    {
        $classifier = $this->app->getContainer()->get(ClassifierInterface::class);
        $ticket = Ticket::fromRow($this->validTicketRow());

        $result = $classifier->classify($ticket);

        $this->assertIsString($result->reasoning);
    }

    /**
     * Req 6.5 — keywords is an array
     */
    public function test_classification_result_keywords_is_array(): void
    {
        $classifier = $this->app->getContainer()->get(ClassifierInterface::class);
        $ticket = Ticket::fromRow($this->validTicketRow());

        $result = $classifier->classify($ticket);

        $this->assertIsArray($result->keywords);
    }

    /**
     * Req 6.6 — ClassificationResult is readonly with public properties matching constructor args
     */
    public function test_classification_result_is_readonly_with_public_properties(): void
    {
        $result = new ClassificationResult(
            suggestedCategory: Category::TechnicalIssue,
            suggestedPriority: Priority::High,
            confidence: 0.85,
            reasoning: 'Matched technical keywords',
            keywords: ['error', 'crash'],
        );

        $this->assertSame(0.85, $result->confidence);
        $this->assertSame('Matched technical keywords', $result->reasoning);
        $this->assertSame(['error', 'crash'], $result->keywords);
        $this->assertSame(Category::TechnicalIssue, $result->suggestedCategory);
        $this->assertSame(Priority::High, $result->suggestedPriority);
    }

    /**
     * Req 6.7 — TicketService::create with autoClassify persists classification fields
     */
    public function test_ticket_service_create_with_auto_classify_persists_classification(): void
    {
        $mockClassifier = $this->createMock(ClassifierInterface::class);
        $mockClassifier->method('classify')->willReturn(new ClassificationResult(
            suggestedCategory: Category::TechnicalIssue,
            suggestedPriority: Priority::High,
            confidence: 0.9,
            reasoning: 'test reasoning',
            keywords: ['test'],
        ));

        $this->app->getContainer()->set(ClassifierInterface::class, $mockClassifier);

        $response = $this->postJson('/tickets?auto_classify=true', $this->validTicketData());

        $this->assertSame(201, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame(0.9, $data['classification']['confidence']);
        $this->assertSame('test reasoning', $data['classification']['reasoning']);
        $this->assertSame(['test'], $data['classification']['keywords']);
    }
}
