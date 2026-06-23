# Task 6 — Orchestrator / Integrator

**Date:** 2026-06-23

## Research / Decisions

### No new library research needed

`research-notes.md` already covers all dependencies. No context7 query was
required — brick/math and PHPUnit 12 are already installed and all shared
infrastructure (FileQueue, Envelope, AuditLogger, stages) exists from Tasks 2–5.

### Output-dir sequencing / draining approach

All three stages share the `output/` directory as a hand-off point:
- Validator reads from `input/`, writes validated to `output/` (target=fraud_detector),
  rejected go directly to `results/`.
- FraudDetector reads from `output/`, writes low-risk back to `output/` (target=settlement),
  high-risk go to `results/`.
- Settlement reads from `output/`, writes all to `results/`.

**Critical observation:** None of the stages filter files in `output/` by the `target`
field — they process ALL files present in the directory at the time they run.
This means strict sequential execution is mandatory:
1. Validator fully drains `input/` → `output/` is then populated with validated-only files.
2. FraudDetector fully drains `output/` → `output/` now contains only low-risk files (target=settlement).
3. Settlement fully drains `output/` → all transactions are in `results/`.

No double-processing or skipping can occur because each stage drains its source
completely before the next stage starts writing to the same directory.

### Malformed / empty input handling

- **Empty input file** (`[]`): `inputCount = 0`, `resultCount = 0` → reconciliation
  passes (0 == 0); returns exit code 0. A "No transactions to process" message is emitted.
- **Malformed top-level JSON** (unparseable): `json_decode` throws `JsonException`,
  caught and traced; returns exit code 1.
- **Top-level non-array** (JSON scalar or object decoded as non-array via `is_array`):
  traced as ERROR; returns exit code 1. Note: a JSON object decoded with
  `associative:true` is a PHP array, so its values are iterated and each non-array
  value is skipped with a WARNING.
- **Non-object record within array** (scalar element): skipped with WARNING, not
  enqueued; `inputCount` does not count skipped records. If all valid records still
  reach `results/`, reconciliation passes.

### Exit codes

- `0` — all enqueued transactions reached `results/` (reconciliation passes).
- `1` — fatal input error (file not found, unreadable, malformed JSON) OR
  reconciliation mismatch (resultCount ≠ inputCount).

### Injectable sinks

- **Output sink** (`$sink` — callable|null): progress traces; defaults to STDOUT.
  Tests inject a silent or capturing sink.
- **AuditLogger** (`$logger` — AuditLogger|null): defaults to `new AuditLogger()`
  (which writes to STDERR). Tests inject `new AuditLogger(sink: fn() => null)` so
  no audit log lines bleed through during `make test`.

## What was created / changed

- **`src/Pipeline/Integrator.php`** — new file.
  - Constructor: `__construct(string $baseDir, callable|null $sink, AuditLogger|null $logger)`.
  - `run(string $inputFile): int` — idempotent setup, clear prior state, load input,
    strictly sequential stage execution, reconciliation.
  - Private `loadInputFile()` handles empty/malformed records defensively.
  - Private `emit()` routes through the injected sink.

- **`bin/run-pipeline`** — new CLI entrypoint (PHP shebang, `chmod +x`).
  - Resolves project root from `dirname(__DIR__)`.
  - Bootstraps `vendor/autoload.php` (error if missing).
  - Constructs `Integrator` with default stdout sink and default AuditLogger (STDERR).
  - Exits with the integrator's return code.

- **`tests/Pipeline/IntegratorTest.php`** — 20 tests covering:
  - Happy path: single and multiple valid transactions, settled with correct fee/net.
  - Mixed fixture: all rejection types (bad currency, negative amount, high-risk fraud).
  - Full sample-transactions.json fixture (8 transactions, 5 settled / 3 rejected).
  - Sink behaviour: capturing sink receives output; silent sink emits nothing to STDOUT.
  - Empty input: exit code 0, no results, "No transactions to process" message.
  - Malformed JSON (top-level): exit code 1, ERROR message.
  - Malformed record (non-object element): skipped with WARNING, remaining records
    processed normally.
  - All-non-object records: exit code 0 (inputCount=0, resultCount=0).
  - Top-level JSON scalar (not an array): exit code 1.
  - Dirty shared/ re-run: prior state cleared, new run starts clean.

## Self-verification

### `make test` (all 214 tests)

```
docker compose run --rm app php vendor/bin/phpunit --no-coverage
PHPUnit 12.5.30 by Sebastian Bergmann and contributors.
Runtime: PHP 8.4.22
...............................................................  63 / 214 ( 29%)
............................................................... 126 / 214 ( 58%)
............................................................... 189 / 214 ( 88%)
.........................                                       214 / 214 (100%)
Time: 00:00.170, Memory: 16.00 MB
OK (214 tests, 449 assertions)
```

- Zero deprecations.
- Zero pipeline/application output bleeding through (audit log correctly suppressed
  by silent AuditLogger injected in all tests; progress traces suppressed by
  silent/capturing sinks).

### `make run` (end-to-end on sample-transactions.json)

8 transactions processed; 8 result files in `shared/results/`:

| Transaction | Status   | Notes                                              |
|-------------|----------|----------------------------------------------------|
| TXN001      | settled  | $1500.00; fee=$3.75, net=$1496.25                  |
| TXN002      | settled  | $25000.00 (high value, score=40 < 60); fee=$62.50  |
| TXN003      | settled  | $9999.99 (below threshold); fee=$25.00             |
| TXN004      | rejected | unusual_hour + cross_border = score 60 (high-risk) |
| TXN005      | settled  | $75000.00 (high value, score=40 < 60); fee=$187.50 |
| TXN006      | rejected | invalid currency XYZ                               |
| TXN007      | rejected | negative amount -100.00                            |
| TXN008      | settled  | $3200.00; fee=$8.00, net=$3192.00                  |

**Result: 5 settled, 3 rejected. Reconciliation: 8/8. Exit code 0.**
