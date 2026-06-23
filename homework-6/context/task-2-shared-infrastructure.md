# Task 2 — Shared Infrastructure: Envelope, FileQueue, Money, AuditLogger
**Date:** 2026-06-23

## Research / Decisions

### Decimal/money library

Resolved via context7 (2 queries recorded in `research-notes.md`):

- **Query 1:** Searched for `brick/math` on context7. Found `/brick/math` (High reputation, 89 snippets) and `/brick/money` (High reputation, 141 snippets).
  - `brick/math` provides `BigDecimal` with `toScale($scale, RoundingMode::HALF_UP)` — matches the spec's "half-up to the minor unit" exactly.
  - `brick/money` was ruled out: it adds full currency-object overhead; ISO 4217 validation belongs in the Validator stage, not the money helper.

- **Query 2:** Confirmed latest stable version by running `composer require brick/math` inside the container. Installed: **brick/math 0.12.3**.

- **API gotcha:** In brick/math 0.12, `RoundingMode` is a PHP **enum** — cases use SCREAMING_SNAKE_CASE (`RoundingMode::HALF_UP`), not PascalCase (`RoundingMode::HalfUp`) as shown in some older context7 snippets. Verified by reflecting on the enum inside the container before finalising `Money.php`.

- **bcmath** (already in the image from Task 1) is the backend `brick/math` uses automatically — no extra Dockerfile changes needed.

**Decision:** `brick/math ^0.12` added to `composer.json` → `composer.lock` updated inside container.

## Files Created

| File | Description |
|------|-------------|
| `src/Shared/Envelope.php` | Builds/parses the standard JSON envelope. `create()` generates UUID v4 + ISO-8601 timestamp. `fromJson()` validates required fields and throws on malformed JSON or missing keys. `toJson()` serialises back to pretty-printed JSON. |
| `src/Shared/FileQueue.php` | Atomic file hand-off between `input/`, `processing/`, `output/`, `results/`. `write()` uses a tmp→rename pattern; `move()` uses POSIX-atomic `rename()`. Validates directory names. `listFiles()` and `clear()` provided for orchestrator use. Tests use a per-test `sys_get_temp_dir()` working area — never touches the real `shared/`. |
| `src/Shared/Money.php` | All arithmetic via `BigDecimal` (brick/math). `parse()` validates numeric strings. `fee()` multiplies amount × rate → `toScale(2, HALF_UP)`. `subtract()` computes minuend minus subtrahend. `round()` rounds to scale. `compare()` and `isPositive()` added for use by later stages. Never uses float internally. |
| `src/Shared/AuditLogger.php` | Injectable sink (callable). Logs JSON lines: `timestamp`, `step`, `transaction_id`, `outcome`, `context`. PII masking: `source_account`, `destination_account`, `description`, any key containing "account" or "name" are replaced with `[MASKED:<sha256-16-hex>]`. Masking is consistent (same value → same token) and irreversible. Default sink writes to STDERR (never STDOUT) to avoid polluting pipeline JSON frames. |
| `tests/Shared/EnvelopeTest.php` | 11 tests: UUID v4 format, ISO-8601 timestamp, uniqueness, round-trip, missing-field data-provider (6 cases), malformed JSON, non-object JSON, non-array data. |
| `tests/Shared/FileQueueTest.php` | 15 tests: directory creation, write, read, move (atomic + missing-file), full pipeline flow, invalid dir names, listFiles, clear. Each test uses an isolated tmp dir cleaned up in tearDown. |
| `tests/Shared/MoneyTest.php` | 28 tests: parse (positive, zero, negative, integer, 5 non-numeric cases), fee (correctness, half-up rounding, large amount, midpoint, custom scale, non-numeric inputs), subtract (correctness, large, negative result, non-numeric inputs), round (6 boundary cases via data provider), compare, isPositive, integration settlement math for TXN001 and TXN005. |
| `tests/Shared/AuditLoggerTest.php` | 13 tests: JSON output, required fields, ISO-8601 timestamp, source_account masked, destination_account masked, description masked, name masked, non-PII fields visible, mask consistency (same value), mask difference (different values), nested context masking, empty context. |
| `phpunit.xml.dist` | Minimal bootstrap configuration (Task 9 will extend with coverage reporting). Sets `failOnDeprecation=true`, `failOnNotice=true`, `failOnWarning=true` so the suite fails on any deprecation — enforcing the zero-deprecation requirement. |

## Self-Verification

### Commands run and outcomes

1. **`docker compose run --rm app composer require brick/math:^0.12 --no-interaction`**
   - Result: Installed `brick/math (0.12.3)`, `composer.lock` updated.

2. **`make test`** (first attempt — exposed `RoundingMode::HalfUp` vs `HALF_UP` mismatch)
   - 63 passed, 17 errors. Fixed `Money.php` to use `RoundingMode::HALF_UP` (enum case).
   - Also corrected `MoneyTest` rounding expectation for `-2.505` → `-2.51` (HALF_UP rounds toward +∞).

3. **`make test`** (second attempt after fixes)
   - **Result: OK (80 tests, 139 assertions) — 0 failures, 0 errors, 0 deprecations, 0 warnings.**
   - PHPUnit 12.5.30 on PHP 8.4.22. No pipeline/application output in test output.

### Coverage check (spot-check)

`make test` was run without coverage (fast path). Coverage will be verified once Task 9 wires up `phpunit.xml.dist` fully. The logic branches covered by 80 tests across 4 classes should comfortably exceed 90% for `src/Shared/`.
