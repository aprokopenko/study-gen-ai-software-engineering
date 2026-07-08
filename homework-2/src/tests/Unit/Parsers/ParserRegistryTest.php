<?php

declare(strict_types=1);

namespace Tests\Unit\Parsers;

use App\Parsers\ParseException;
use App\Parsers\ParserRegistry;
use App\Parsers\TicketImportParserInterface;
use PHPUnit\Framework\TestCase;

class ParserRegistryTest extends TestCase
{
    private TicketImportParserInterface $csvParser;
    private TicketImportParserInterface $jsonParser;
    private TicketImportParserInterface $xmlParser;
    private ParserRegistry $registry;

    protected function setUp(): void
    {
        $this->csvParser = $this->createStub(TicketImportParserInterface::class);
        $this->jsonParser = $this->createStub(TicketImportParserInterface::class);
        $this->xmlParser = $this->createStub(TicketImportParserInterface::class);

        $this->registry = new ParserRegistry([
            'text/csv' => $this->csvParser,
            'application/json' => $this->jsonParser,
            'application/xml' => $this->xmlParser,
            'text/xml' => $this->xmlParser,
        ]);
    }

    /**
     * Req 8.1 — resolving 'text/csv' returns the CSV parser.
     */
    public function test_resolve_csv_content_type(): void
    {
        $result = $this->registry->resolve('text/csv');

        $this->assertSame($this->csvParser, $result);
    }

    /**
     * Req 8.2 — resolving 'application/json' returns the JSON parser.
     */
    public function test_resolve_json_content_type(): void
    {
        $result = $this->registry->resolve('application/json');

        $this->assertSame($this->jsonParser, $result);
    }

    /**
     * Req 8.3 — both 'application/xml' and 'text/xml' return the XML parser.
     */
    public function test_resolve_xml_content_types(): void
    {
        $resultAppXml = $this->registry->resolve('application/xml');
        $resultTextXml = $this->registry->resolve('text/xml');

        $this->assertSame($this->xmlParser, $resultAppXml);
        $this->assertSame($this->xmlParser, $resultTextXml);
    }

    /**
     * Req 8.4 — charset parameters are stripped before lookup.
     */
    public function test_resolve_strips_charset_parameter(): void
    {
        $result = $this->registry->resolve('application/json; charset=utf-8');

        $this->assertSame($this->jsonParser, $result);
    }

    /**
     * Req 8.5 — unsupported content type throws ParseException.
     */
    public function test_resolve_unsupported_type_throws_parse_exception(): void
    {
        $this->expectException(ParseException::class);

        $this->registry->resolve('text/plain');
    }

    /**
     * Req 8.6 — falls back to ?format= parameter when content type is unsupported.
     */
    public function test_resolve_falls_back_to_format_parameter(): void
    {
        $jsonResult = $this->registry->resolve('text/plain', 'json');
        $this->assertSame($this->jsonParser, $jsonResult);

        $csvResult = $this->registry->resolve('text/plain', 'csv');
        $this->assertSame($this->csvParser, $csvResult);

        $xmlResult = $this->registry->resolve('text/plain', 'xml');
        $this->assertSame($this->xmlParser, $xmlResult);
    }
}
