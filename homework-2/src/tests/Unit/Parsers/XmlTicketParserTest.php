<?php

declare(strict_types=1);

namespace Tests\Unit\Parsers;

use App\Parsers\ParseException;
use App\Parsers\XmlTicketParser;
use PHPUnit\Framework\TestCase;

class XmlTicketParserTest extends TestCase
{
    private XmlTicketParser $parser;

    protected function setUp(): void
    {
        $this->parser = new XmlTicketParser();
    }

    /**
     * Req 7.1 — valid XML yields associative arrays with customer_id key.
     */
    public function test_parse_valid_xml_yields_associative_arrays(): void
    {
        $content = file_get_contents(__DIR__ . '/../../fixtures/valid/sample_tickets.xml');
        $this->assertNotFalse($content);

        $results = iterator_to_array($this->parser->parse($content));

        $this->assertGreaterThanOrEqual(1, count($results));

        foreach ($results as $row) {
            $this->assertIsArray($row);
            $this->assertArrayHasKey('customer_id', $row);
        }
    }

    /**
     * Req 7.2 — XML with nested metadata element yields metadata as an array.
     */
    public function test_parse_xml_with_nested_metadata(): void
    {
        $content = file_get_contents(__DIR__ . '/../../fixtures/valid/sample_tickets.xml');
        $this->assertNotFalse($content);

        $results = iterator_to_array($this->parser->parse($content));

        foreach ($results as $row) {
            if (array_key_exists('metadata', $row)) {
                $this->assertIsArray($row['metadata']);
            }
        }
    }

    /**
     * Req 7.3 — XML with tags elements yields tags as an array of strings.
     */
    public function test_parse_xml_with_tags_elements(): void
    {
        $content = file_get_contents(__DIR__ . '/../../fixtures/valid/sample_tickets.xml');
        $this->assertNotFalse($content);

        $results = iterator_to_array($this->parser->parse($content));

        foreach ($results as $row) {
            if (array_key_exists('tags', $row) && $row['tags'] !== null) {
                $this->assertIsArray($row['tags']);
                foreach ($row['tags'] as $tag) {
                    $this->assertIsString($tag);
                }
            }
        }
    }

    /**
     * Req 7.4 — empty string throws ParseException.
     */
    public function test_parse_empty_string_throws_parse_exception(): void
    {
        $this->expectException(ParseException::class);

        iterator_to_array($this->parser->parse(''));
    }

    /**
     * Req 7.5 — malformed XML throws ParseException.
     */
    public function test_parse_malformed_xml_throws_parse_exception(): void
    {
        $content = file_get_contents(__DIR__ . '/../../fixtures/invalid/malformed.xml');
        $this->assertNotFalse($content);

        $this->expectException(ParseException::class);

        iterator_to_array($this->parser->parse($content));
    }

    /**
     * Req 7.6 — XML with DTD/entity declarations throws ParseException mentioning DTD or entity.
     */
    public function test_parse_xxe_xml_throws_parse_exception(): void
    {
        $content = file_get_contents(__DIR__ . '/../../fixtures/invalid/xxe.xml');
        $this->assertNotFalse($content);

        $this->expectException(ParseException::class);
        $this->expectExceptionMessageMatches('/DTD|entity/i');

        iterator_to_array($this->parser->parse($content));
    }
}
