<?php

declare(strict_types=1);

namespace App\Parsers;

interface TicketImportParserInterface
{
    /**
     * Parse raw bytes into normalized assoc rows.
     *
     * @return iterable<array<string,mixed>>
     * @throws ParseException
     */
    public function parse(string $raw): iterable;
}
