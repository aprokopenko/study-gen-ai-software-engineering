<?php

declare(strict_types=1);

namespace Tests\Unit\Parsers;

use App\Parsers\JsonTicketParser;
use App\Parsers\ParseException;
use PHPUnit\Framework\TestCase;

class JsonTicketParserTest extends TestCase
{
    private JsonTicketParser $parser;

    protected function setUp(): void
    {
        $this->parser = new JsonTicketParser();
    }

    /**
     * Req 5.1 — valid JSON top-level array yields associative arrays with customer_id key.
     */
    public function test_parse_valid_json_array_yields_associative_arrays(): void
    {
        $content = file_get_contents(__DIR__ . '/../../fixtures/valid/sample_tickets.json');
        $this->assertNotFalse($content);

        $results = iterator_to_array($this->parser->parse($content));

        $this->assertGreaterThanOrEqual(1, count($results));

        foreach ($results as $row) {
            $this->assertIsArray($row);
            $this->assertArrayHasKey('customer_id', $row);
        }
    }

    /**
     * Req 5.2 — JSON with top-level "tickets" key yields the nested array items.
     */
    public function test_parse_json_with_tickets_key_yields_nested_array(): void
    {
        $json = json_encode(['tickets' => [['customer_id' => 'cust-1', 'subject' => 'Test']]]);
        $this->assertNotFalse($json);

        $results = iterator_to_array($this->parser->parse($json));

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('customer_id', $results[0]);
        $this->assertSame('cust-1', $results[0]['customer_id']);
    }

    /**
     * Req 5.3 — empty string throws ParseException.
     */
    public function test_parse_empty_string_throws_parse_exception(): void
    {
        $this->expectException(ParseException::class);

        iterator_to_array($this->parser->parse(''));
    }

    /**
     * Req 5.4 — malformed JSON throws ParseException with a JSON error description.
     */
    public function test_parse_malformed_json_throws_parse_exception(): void
    {
        $content = file_get_contents(__DIR__ . '/../../fixtures/invalid/malformed.json');
        $this->assertNotFalse($content);

        $this->expectException(ParseException::class);
        $this->expectExceptionMessageMatches('/json/i');

        iterator_to_array($this->parser->parse($content));
    }

    /**
     * Req 5.5 — non-array JSON (e.g. a bare string) throws ParseException.
     */
    public function test_parse_non_array_json_throws_parse_exception(): void
    {
        $this->expectException(ParseException::class);

        iterator_to_array($this->parser->parse('"just a string"'));
    }
}
