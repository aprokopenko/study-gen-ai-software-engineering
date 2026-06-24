# Task 5 — Settlement Stage

**Date:** 2026-06-23

## Decisions

### Fee rate constant

- Location: `src/Config/SettlementConfig.php`
- Constant: `SettlementConfig::FEE_RATE = '0.0025'` (decimal string for 0.25%)
- Companion: `SettlementConfig::FEE_RATE_DESCRIPTION = '0.25%'` (human-readable, for logs/docs)
- Class is a constants-only container (private constructor), matching `FraudRules` pattern.

### Rounding and reconciliation rule

Fee is computed first via `Money::fee($amount, FEE_RATE)` which applies `brick/math`
`BigDecimal::multipliedBy()->toScale(2, RoundingMode::HALF_UP)`. Net is then computed
via `Money::subtract($roundedFee, ...)` which rounds to 2 decimal places.

**Reconciliation rule (documented in SettlementConfig):** The fee is authoritative — it
is rounded half-up to the currency minor unit (2 decimal places) first. The net is the
exact remainder: `net = amount − rounded_fee`. Because validated transaction amounts are
2-decimal strings, `fee + net == amount` exactly for all standard inputs. In the
pathological case where an amount has sub-cent precision, a ±0.01 difference between
`(fee + net)` and `amount` is acceptable and documented; the fee is the commercially
authoritative figure.

### No new library required

`brick/math` (already installed, `^0.12`) provides all needed operations via
`Money::fee()` and `Money::subtract()`. No `research-notes.md` update needed.

## What was created

| File | Purpose |
|------|---------|
| `src/Config/SettlementConfig.php` | `FEE_RATE` + `FEE_RATE_DESCRIPTION` constants with full reconciliation-rule docblock |
| `src/Stages/Settlement.php` | Settlement stage: `process(array): array` (pure) + `run()` (queue orchestration) |
| `tests/Stages/SettlementTest.php` | 37 unit tests covering all spec edge cases |

### Settlement.php structure

Mirrors `FraudDetector.php` exactly:
- `__construct(FileQueue, AuditLogger, string $baseDir)` — injectable deps
- `run(): array{settled: int}` — reads `output/`, moves to `processing/`, writes to `results/`, cleans `processing/`
- `process(array $message): array` — pure: computes fee/net, sets `status=settled`, logs audit

### Test coverage (37 tests, 65 assertions)

- Happy path (status, fee string, net string, field preservation)
- Data-provider cases: 8 amount/fee/net combinations (typical, boundary, tiny)
- Very large amounts: `9999999.99` and `99999999999.00` — confirms no float precision loss
- Rounding half-up: `2.00 → fee=0.01` (0.005 rounds up); `202.00 → fee=0.51` (0.505 rounds up)
- Rounding down: `1.60 → fee=0.00` (0.004 rounds down)
- Reconciliation: `fee + net == amount` verified with `bcadd()` for 5 distinct cases
- Audit log: outcome=settled, correct transaction_id, amount/fee/net in context
- PII: source_account and destination_account never appear in log
- `run()`: output→processing→results flow, correct envelope source/target, processing cleared, output cleared, multi-message, empty queue

## Self-verification

```
make test
# → PHPUnit 12.5.30 / PHP 8.4.22 / 196 tests, 398 assertions — OK

docker compose run --rm app php vendor/bin/phpunit --no-coverage --filter SettlementTest
# → 37 tests, 65 assertions — OK
```

Zero deprecations. No application output. Only runner progress lines visible.
Previous 159 tests still pass; total went from 159 → 196.
