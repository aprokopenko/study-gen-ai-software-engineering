<?php

declare(strict_types=1);

namespace BankingPipeline\Pipeline;

use BankingPipeline\Stages\Validator;
use BankingPipeline\Shared\AuditLogger;
use BankingPipeline\Shared\FileQueue;

/**
 * Dry-run validation reporter.
 *
 * Loads a JSON file of raw transaction records and runs each record through
 * the Validator's pure process() method — no file queue, no fraud scoring, no
 * settlement, and nothing written to shared/.
 *
 * Produces:
 *   - A structured array of per-transaction results (used by tests and the CLI).
 *   - A human-readable table printed via an injectable output sink.
 *
 * The output sink defaults to STDOUT when null is passed so the CLI prints
 * normally; tests inject a silent callable or a capturing buffer.
 *
 * ## Dry-run guarantee
 * The Validator is constructed with a no-op FileQueue (injected via constructor
 * injection) and a no-op AuditLogger so no filesystem side-effects occur.
 * process() is a pure function: it only reads the message array it is given.
 *
 * ## Input format
 * The input file must be a JSON array of raw transaction objects (the same shape
 * as sample-transactions.json). Each object is the raw data — not already wrapped
 * in a pipeline envelope. Example record:
 *
 *   { "transaction_id": "TXN001", "amount": "1500.00", "currency": "USD", ... }
 */
final class ValidationReport
{
    /** @var callable(string): void */
    private $sink;

    /**
     * @param callable|null $sink  Output sink: a callable that accepts a string.
     *                             Null means write to STDOUT (CLI default).
     */
    public function __construct(
        ?callable $sink = null,
    ) {
        $this->sink = $sink ?? static function (string $line): void {
            fwrite(STDOUT, $line);
        };
    }

    /**
     * Load a transaction file, validate each record, and print + return results.
     *
     * @param  string $inputFile  Absolute or relative path to the JSON input file.
     * @return array{
     *   total: int,
     *   valid: int,
     *   invalid: int,
     *   rows: list<array{transaction_id: string, result: string, reason: string}>,
     * }
     *
     * @throws \RuntimeException  When the file cannot be read or is not valid JSON.
     */
    public function run(string $inputFile): array
    {
        $transactions = $this->loadTransactions($inputFile);

        // Build a no-op Validator: the FileQueue and AuditLogger are never
        // exercised because we only call process(), not run().
        $noopLogger    = new AuditLogger(sink: static fn() => null);
        $noopQueue     = new FileQueue(baseDir: sys_get_temp_dir());
        $validator     = new Validator(queue: $noopQueue, logger: $noopLogger);

        $rows    = [];
        $valid   = 0;
        $invalid = 0;

        foreach ($transactions as $txn) {
            $result = $validator->process((array) $txn);
            $status = $result['status'] ?? 'invalid';
            $reason = $result['reason'] ?? '';
            $txnId  = (string) ($txn['transaction_id'] ?? '(unknown)');

            if ($status === 'validated') {
                $valid++;
                $rows[] = ['transaction_id' => $txnId, 'result' => 'valid',   'reason' => ''];
            } else {
                $invalid++;
                $rows[] = ['transaction_id' => $txnId, 'result' => 'invalid', 'reason' => $reason];
            }
        }

        $total = $valid + $invalid;

        $this->printReport(filename: $inputFile, total: $total, valid: $valid, invalid: $invalid, rows: $rows);

        return compact('total', 'valid', 'invalid', 'rows');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Load and JSON-decode the input file.
     *
     * @return list<mixed>
     * @throws \RuntimeException
     */
    private function loadTransactions(string $inputFile): array
    {
        $json = @file_get_contents($inputFile);
        if ($json === false) {
            throw new \RuntimeException("Cannot read input file: {$inputFile}");
        }

        try {
            $decoded = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException("Invalid JSON in {$inputFile}: {$e->getMessage()}");
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException("Expected a JSON array in {$inputFile}");
        }

        return array_values($decoded);
    }

    /**
     * Print the validation report table via the injected sink.
     *
     * @param list<array{transaction_id: string, result: string, reason: string}> $rows
     */
    private function printReport(
        string $filename,
        int $total,
        int $valid,
        int $invalid,
        array $rows,
    ): void {
        $emit = $this->sink;

        $label = basename($filename);
        $emit("Validation Results — {$label}" . PHP_EOL);
        $emit(str_repeat('=', 40 + strlen($label)) . PHP_EOL);
        $emit(sprintf("Total : %d   Valid : %d   Invalid : %d" . PHP_EOL, $total, $valid, $invalid));
        $emit(PHP_EOL);

        if ($rows === []) {
            $emit("(no transactions found)" . PHP_EOL);
            return;
        }

        // Column widths: TXN ID, Result, Reason
        $idWidth     = max(6, ...array_map(static fn($r) => strlen($r['transaction_id']), $rows));
        $resultWidth = 7; // "invalid"
        $reasonWidth = max(6, ...array_map(static fn($r) => strlen($r['reason']), $rows));
        $reasonWidth = max($reasonWidth, 6); // minimum label width

        $divider = sprintf(
            '+%s+%s+%s+',
            str_repeat('-', $idWidth + 2),
            str_repeat('-', $resultWidth + 2),
            str_repeat('-', $reasonWidth + 2),
        );

        $header = sprintf(
            '| %-' . $idWidth . 's | %-' . $resultWidth . 's | %-' . $reasonWidth . 's |',
            'TXN ID',
            'Result',
            'Reason',
        );

        $emit($divider . PHP_EOL);
        $emit($header . PHP_EOL);
        $emit($divider . PHP_EOL);

        foreach ($rows as $row) {
            $emit(sprintf(
                '| %-' . $idWidth . 's | %-' . $resultWidth . 's | %-' . $reasonWidth . 's |' . PHP_EOL,
                $row['transaction_id'],
                $row['result'],
                $row['reason'],
            ));
        }

        $emit($divider . PHP_EOL);
    }
}
