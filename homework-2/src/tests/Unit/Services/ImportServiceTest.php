<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Entities\Ticket;
use App\Parsers\ParseException;
use App\Parsers\ParserRegistry;
use App\Parsers\TicketImportParserInterface;
use App\Services\ImportService;
use App\Services\TicketService;
use App\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use Tests\Traits\TicketDataBuilder;

class ImportServiceTest extends TestCase
{
    use TicketDataBuilder;

    /**
     * Req 9.1 — valid content returns correct summary
     */
    public function test_import_valid_content_returns_correct_summary(): void
    {
        $rows = [
            $this->validTicketData(),
            $this->validTicketData(['customer_email' => 'a@example.com']),
            $this->validTicketData(['customer_email' => 'b@example.com']),
        ];

        $parserMock = $this->createMock(TicketImportParserInterface::class);
        $parserMock->method('parse')->willReturn($rows);

        $registryMock = $this->createMock(ParserRegistry::class);
        $registryMock->method('resolve')->willReturn($parserMock);

        $ticketServiceMock = $this->createMock(TicketService::class);
        $ticketServiceMock->method('create')->willReturn($this->createMock(Ticket::class));

        $importService = new ImportService($registryMock, $ticketServiceMock);
        $summary = $importService->import('content', 'text/csv');

        $this->assertSame(3, $summary->total);
        $this->assertSame(3, $summary->successful);
        $this->assertSame(0, $summary->failed);
    }

    /**
     * Req 9.2 — validation failures continue processing
     */
    public function test_import_with_validation_failures_continues_processing(): void
    {
        $rows = [
            $this->validTicketData(),
            $this->validTicketData(['customer_email' => 'invalid-email']),
            $this->validTicketData(['customer_email' => 'c@example.com']),
        ];

        $parserMock = $this->createMock(TicketImportParserInterface::class);
        $parserMock->method('parse')->willReturn($rows);

        $registryMock = $this->createMock(ParserRegistry::class);
        $registryMock->method('resolve')->willReturn($parserMock);

        $ticketServiceMock = $this->createMock(TicketService::class);

        $callCount = 0;
        $ticketServiceMock->expects($this->exactly(3))
            ->method('create')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 2) {
                    throw new ValidationException(['customer_email' => 'Invalid email']);
                }
                return $this->createMock(Ticket::class);
            });

        $importService = new ImportService($registryMock, $ticketServiceMock);
        $summary = $importService->import('content', 'text/csv');

        $this->assertSame(3, $summary->total);
        $this->assertSame(2, $summary->successful);
        $this->assertSame(1, $summary->failed);
        $this->assertCount(1, $summary->errors);
        $this->assertSame('customer_email', $summary->errors[0]['field']);
    }

    /**
     * Req 9.3 — parse failure returns error summary
     */
    public function test_import_parse_failure_returns_error_summary(): void
    {
        $parserMock = $this->createMock(TicketImportParserInterface::class);
        $parserMock->method('parse')->willThrowException(new ParseException('Parse failed'));

        $registryMock = $this->createMock(ParserRegistry::class);
        $registryMock->method('resolve')->willReturn($parserMock);

        $ticketServiceMock = $this->createMock(TicketService::class);

        $importService = new ImportService($registryMock, $ticketServiceMock);
        $summary = $importService->import('content', 'text/csv');

        $this->assertSame(0, $summary->total);
        $this->assertSame(0, $summary->successful);
        $this->assertSame(1, $summary->failed);
        $this->assertStringContainsString('Parse failed', $summary->errors[0]['message']);
    }

    /**
     * Req 9.4 — unsupported content type propagates exception
     */
    public function test_import_unsupported_content_type_propagates_exception(): void
    {
        $registryMock = $this->createMock(ParserRegistry::class);
        $registryMock->method('resolve')->willThrowException(new ParseException('Unsupported content type'));

        $ticketServiceMock = $this->createMock(TicketService::class);

        $importService = new ImportService($registryMock, $ticketServiceMock);

        $this->expectException(ParseException::class);
        $importService->import('content', 'text/plain');
    }
}
