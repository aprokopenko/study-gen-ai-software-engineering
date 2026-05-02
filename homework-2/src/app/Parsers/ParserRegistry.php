<?php

declare(strict_types=1);

namespace App\Parsers;

class ParserRegistry
{
    /** @param array<string, TicketImportParserInterface> $parsers keyed by content-type */
    public function __construct(private readonly array $parsers = []) {}

    /**
     * Resolve parser by Content-Type header or ?format= query param.
     * format values: csv, json, xml
     */
    public function resolve(string $contentType, ?string $format = null): TicketImportParserInterface
    {
        // Normalize content-type (strip params like charset)
        $mime = strtolower(trim(explode(';', $contentType)[0]));

        if (isset($this->parsers[$mime])) {
            return $this->parsers[$mime];
        }

        // Fallback to ?format= param
        if ($format !== null) {
            $map = [
                'csv' => 'text/csv',
                'json' => 'application/json',
                'xml' => 'application/xml',
            ];
            $resolved = $map[strtolower($format)] ?? null;
            if ($resolved !== null && isset($this->parsers[$resolved])) {
                return $this->parsers[$resolved];
            }
        }

        throw new ParseException("Unsupported content type: {$contentType}");
    }
}
