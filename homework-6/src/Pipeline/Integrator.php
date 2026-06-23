<?php

declare(strict_types=1);

namespace BankingPipeline\Pipeline;

use BankingPipeline\Shared\AuditLogger;
use BankingPipeline\Shared\Envelope;
use BankingPipeline\Shared\FileQueue;
use BankingPipeline\Stages\FraudDetector;
use BankingPipeline\Stages\Settlement;
use BankingPipeline\Stages\Validator;

/**
 * Pipeline orchestrator / integrator.
 *
 * Runs the full transaction-processing pipeline end-to-end:
 *   1. Creates the shared/ directory tree if missing (idempotent).
 *   2. Clears prior run state so each run starts clean.
 *   3. Loads the input JSON file, wraps each record in the standard Envelope,
 *      and drops it into shared/input.
 *   4. Runs the stages strictly in order: Validator → FraudDetector → Settlement.
 *      Because all three stages share the output/ directory as a hand-off point,
 *      strict sequential execution is essential:
 *        - Validator drains input/ and writes validated to output/ (target=fraud_detector)
 *          or rejected to results/ — output/ is empty before FraudDetector starts.
 *        - FraudDetector drains output/ and writes low-risk back to output/
 *          (target=settlement) or rejected to results/ — output/ is now empty before
 *          Settlement starts.
 *        - Settlement drains output/ and writes all outcomes to results/.
 *      After all three stages complete, results/ should contain exactly one file
 *      per input transaction.
 *   5. Reconciles the result count against the input count and returns non-zero
 *      if any transaction is unaccounted for.
 *
 * Output sink:
 *   Progress traces are emitted through an injectable callable (default: STDOUT).
 *   Tests inject a silent or capturing sink so no pipeline output bleeds through
 *   during `make test`.
 *
 * Edge cases:
 *   - Empty input file  → 0 results, 0 expected, reconciliation passes, return 0.
 *   - Malformed JSON record in the array → skipped; a trace message is emitted
 *     and the final reconciliation will catch the shortfall (returns 1).
 *   - Malformed top-level JSON (unparseable file) → all records skipped; returns 1.
 *   - Re-running over a dirty shared/ → all dirs cleared at the start of every run.
 */
final class Integrator
{
    private const STAGE_NAME = 'integrator';

    /**
     * @param string        $baseDir Absolute path to the shared/ directory (runtime queues).
     * @param callable|null $sink    Progress-trace output sink. Receives a single string (line).
     *                               Defaults to writing to STDOUT. Inject a no-op in tests.
     * @param AuditLogger|null $logger Audit logger for stage events. Defaults to a new logger
     *                                 that writes to STDERR. Inject a silent logger in tests.
     */
    public function __construct(
        private readonly string $baseDir,
        private readonly mixed $sink = null,
        private readonly ?AuditLogger $logger = null,
    ) {}

    /**
     * Run the pipeline end-to-end on the given input file.
     *
     * @param string $inputFile Absolute path to the JSON input file.
     * @return int 0 on success (all transactions reached results/), 1 on failure.
     */
    public function run(string $inputFile): int
    {
        $this->emit("Pipeline starting. Input: {$inputFile}");

        // Step 1: Ensure the shared/ directory tree exists (idempotent).
        $queue = new FileQueue($this->baseDir);
        $queue->initialize();

        // Step 2: Clear prior run state.
        $this->emit('Clearing prior run state...');
        $queue->clear(FileQueue::DIR_INPUT);
        $queue->clear(FileQueue::DIR_PROCESSING);
        $queue->clear(FileQueue::DIR_OUTPUT);
        $queue->clear(FileQueue::DIR_RESULTS);

        // Step 3: Load input file and drop envelopes into shared/input.
        $inputCount = $this->loadInputFile($inputFile, $queue);
        if ($inputCount < 0) {
            // Unrecoverable read/parse error.
            return 1;
        }

        $this->emit("Loaded {$inputCount} transaction(s) into input queue.");

        if ($inputCount === 0) {
            $this->emit('No transactions to process. Done.');
            return 0;
        }

        // Step 4: Run stages in strict sequence.
        // Use the injected logger if provided; fall back to a new default logger
        // (which writes to STDERR — never pollutes the progress-trace sink).
        $logger = $this->logger ?? new AuditLogger();

        $this->emit('--- Stage 1: Validator ---');
        $validator = new Validator($queue, $logger, $this->baseDir);
        $validatorCounts = $validator->run();
        $this->emit(
            sprintf(
                'Validator complete: %d validated, %d rejected.',
                $validatorCounts['validated'],
                $validatorCounts['rejected'],
            )
        );

        $this->emit('--- Stage 2: Fraud Detector ---');
        $fraudDetector = new FraudDetector($queue, $logger, $this->baseDir);
        $fraudCounts = $fraudDetector->run();
        $this->emit(
            sprintf(
                'Fraud Detector complete: %d passed, %d rejected.',
                $fraudCounts['passed'],
                $fraudCounts['rejected'],
            )
        );

        $this->emit('--- Stage 3: Settlement ---');
        $settlement = new Settlement($queue, $logger, $this->baseDir);
        $settlementCounts = $settlement->run();
        $this->emit(
            sprintf(
                'Settlement complete: %d settled.',
                $settlementCounts['settled'],
            )
        );

        // Step 5: Reconcile — results/ must contain exactly one file per input transaction.
        $resultCount = count($queue->listFiles(FileQueue::DIR_RESULTS));
        $this->emit("Reconciliation: {$resultCount} result(s) for {$inputCount} input(s).");

        if ($resultCount !== $inputCount) {
            $this->emit(
                "ERROR: Result count ({$resultCount}) does not match input count ({$inputCount}). "
                . 'Some transactions did not reach a final outcome.'
            );
            return 1;
        }

        $this->emit('Pipeline complete. All transactions reached a final outcome.');
        return 0;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Load the input JSON file and write each record as an Envelope into shared/input.
     *
     * @return int The number of records successfully enqueued, or -1 on a fatal error
     *             (file unreadable or top-level JSON is not an array).
     */
    private function loadInputFile(string $inputFile, FileQueue $queue): int
    {
        if (!file_exists($inputFile)) {
            $this->emit("ERROR: Input file not found: {$inputFile}");
            return -1;
        }

        $raw = file_get_contents($inputFile);
        if ($raw === false) {
            $this->emit("ERROR: Cannot read input file: {$inputFile}");
            return -1;
        }

        // Attempt to decode the top-level JSON.
        try {
            $records = json_decode($raw, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->emit("ERROR: Input file is not valid JSON: {$e->getMessage()}");
            return -1;
        }

        if (!is_array($records)) {
            $this->emit('ERROR: Input file must contain a JSON array of transaction records.');
            return -1;
        }

        // Wrap each record in an Envelope and drop into shared/input.
        $count = 0;
        foreach ($records as $index => $record) {
            if (!is_array($record)) {
                $this->emit("WARNING: Skipping record at index {$index}: not a JSON object.");
                continue;
            }

            // Extract a usable transaction ID for the log trace; fall back to index.
            $txnId = isset($record['transaction_id']) && is_string($record['transaction_id'])
                ? $record['transaction_id']
                : "record-{$index}";

            $envelope = Envelope::create(
                source: self::STAGE_NAME,
                target: 'validator',
                type: 'transaction',
                data: $record,
            );

            $queue->write(FileQueue::DIR_INPUT, $envelope);
            $this->emit("Enqueued transaction: {$txnId}");
            $count++;
        }

        return $count;
    }

    /**
     * Emit a progress-trace line through the injected sink.
     *
     * If no sink is provided, the line is written to STDOUT (the default for the CLI).
     * Tests inject a no-op or capturing callable so no output bleeds through.
     */
    private function emit(string $message): void
    {
        $line = $message . PHP_EOL;

        if ($this->sink !== null) {
            ($this->sink)($line);
        } else {
            fwrite(STDOUT, $line);
        }
    }
}
