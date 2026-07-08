<?php

declare(strict_types=1);

namespace App\Parsers;

use League\Csv\EscapeFormula;
use League\Csv\Exception as CsvException;
use League\Csv\Reader;

class CsvTicketParser implements TicketImportParserInterface
{
    public function parse(string $raw): iterable
    {
        if (trim($raw) === '') {
            throw new ParseException('CSV input is empty');
        }

        try {
            $csv = Reader::fromString($raw);
            $csv->setHeaderOffset(0);
            $csv->addFormatter((new EscapeFormula())->escapeRecord(...));

            foreach ($csv->getRecords() as $record) {
                yield $this->normalize($record);
            }
        } catch (CsvException $e) {
            throw new ParseException('Invalid CSV: ' . $e->getMessage(), 0, $e);
        }
    }

    private function normalize(array $record): array
    {
        $row = [];
        foreach ($record as $key => $value) {
            $row[trim($key)] = $value === '' ? null : $value;
        }

        // tags: comma-separated string → array
        if (isset($row['tags']) && is_string($row['tags'])) {
            $row['tags'] = array_filter(array_map('trim', explode(',', $row['tags'])));
            $row['tags'] = array_values($row['tags']);
        }

        // metadata: flatten metadata_source → metadata.source
        foreach (['source', 'browser', 'device_type'] as $key) {
            if (isset($row["metadata_{$key}"])) {
                $row['metadata'][$key] = $row["metadata_{$key}"] ?: null;
                unset($row["metadata_{$key}"]);
            }
        }

        return $row;
    }
}
