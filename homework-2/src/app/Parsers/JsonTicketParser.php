<?php

declare(strict_types=1);

namespace App\Parsers;

class JsonTicketParser implements TicketImportParserInterface
{
    public function parse(string $raw): iterable
    {
        if (trim($raw) === '') {
            throw new ParseException('JSON input is empty');
        }

        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ParseException('Invalid JSON: ' . json_last_error_msg());
        }

        if (!is_array($data)) {
            throw new ParseException('JSON must be an array of ticket objects');
        }

        // Support both a top-level array and {"tickets": [...]}
        if (isset($data['tickets']) && is_array($data['tickets'])) {
            $data = $data['tickets'];
        }

        foreach ($data as $row) {
            if (!is_array($row)) {
                throw new ParseException('Each JSON element must be an object');
            }
            yield $row;
        }
    }
}
