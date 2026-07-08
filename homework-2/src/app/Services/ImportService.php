<?php

declare(strict_types=1);

namespace App\Services;

use App\Parsers\ParseException;
use App\Parsers\ParserRegistry;
use App\Validation\ValidationException;

class ImportService
{
    public function __construct(
        private readonly ParserRegistry $parsers,
        private readonly TicketService  $ticketService,
    ) {}

    public function import(string $raw, string $contentType, ?string $format = null): ImportSummary
    {
        $parser = $this->parsers->resolve($contentType, $format);

        try {
            $rows = $parser->parse($raw);
        } catch (ParseException $e) {
            return new ImportSummary(total: 0, successful: 0, failed: 1, errors: [[
                'row' => 0,
                'field' => '',
                'message' => $e->getMessage(),
                'raw' => [],
            ]]);
        }

        $total = 0;
        $successful = 0;
        $errors = [];

        foreach ($rows as $row) {
            $total++;
            try {
                $this->ticketService->create($row);
                $successful++;
            } catch (ValidationException $e) {
                foreach ($e->getErrors() as $field => $message) {
                    $errors[] = [
                        'row' => $total,
                        'field' => $field,
                        'message' => $message,
                        'raw' => $row,
                    ];
                }
            } catch (\Throwable $e) {
                $errors[] = [
                    'row' => $total,
                    'field' => '',
                    'message' => $e->getMessage(),
                    'raw' => $row,
                ];
            }
        }

        return new ImportSummary(
            total: $total,
            successful: $successful,
            failed: $total - $successful,
            errors: $errors,
        );
    }
}
