# Task 4 — Fraud Detector Stage

**Date:** 2026-06-23

## Research / decisions

No new library was required. The scoring rules compare amounts using `Money::compare()` from
`brick/math` (already recorded in Task 2 research-notes.md). Timestamp parsing uses PHP's
built-in `DateTimeImmutable`. No Composer dependency was added.

ISO overnight-window boundary check uses `DateTimeImmutable::format('G')` (0–23, no leading
zero) — clean integer comparison against `FraudRules::OVERNIGHT_HOUR_START` / `OVERNIGHT_HOUR_END`.

## What was created

| File | Description |
|------|-------------|
| `src/Config/FraudRules.php` | All fraud rule constants in one documented place: weights (WEIGHT_HIGH_VALUE=40, WEIGHT_UNUSUAL_HOUR=30, WEIGHT_CROSS_BORDER=30), thresholds (HIGH_VALUE_THRESHOLD="10000.00", OVERNIGHT_HOUR_START=0, OVERNIGHT_HOUR_END=5, HOME_COUNTRY="US"), cutoff (CUTOFF=60), and rule identifier strings (RULE_HIGH_VALUE, RULE_UNUSUAL_HOUR, RULE_CROSS_BORDER). |
| `src/Stages/FraudDetector.php` | Fraud detector stage. Pure `process(array): array` scores messages and returns status=fraud_checked (low-risk) or status=high_risk (score ≥ 60). Queue orchestration in `run()` mirrors the Validator pattern: reads from output/, moves to processing/, writes to output/ (low-risk, target=settlement) or results/ (high-risk). |
| `tests/Stages/FraudDetectorTest.php` | 33 unit tests covering: happy path (low-risk forward), each rule in isolation, threshold/cutoff boundaries, missing-country edge case, overnight-window boundaries (00:00, 05:59 trigger; 06:00 does not), combined-rule high-risk (30+30=60), reason string format (rule names + score), audit logging, PII masking, and run() queue orchestration. |

## Design choices

- **Constants container:** `FraudRules` is a `final` class with a `private __construct()` so it cannot
  be instantiated — it is purely a constants namespace.
- **Missing country → cross-border:** `$message['metadata']['country'] ?? null` — when null
  (metadata absent or country key absent), the rule fires (cannot confirm domestic).
- **Status naming:** Low-risk transactions get `status=fraud_checked` (not `validated`), so downstream
  stages (Settlement) can distinguish them. High-risk gets `status=high_risk` (and a `reason` field),
  written to results/ as a rejection.
- **Output sink pattern:** `AuditLogger` is constructed with a capturing `sink` callable in tests
  (same pattern as Validator/AuditLogger tests) — zero pipeline output bleeds into `make test`.

## Self-verification

```
make test
```

Result: **OK (159 tests, 333 assertions)** — all green, no deprecations, no stray output.

- Tests before Task 4: 126 (Tasks 1–3)
- Tests added by Task 4: 33
- Total: 159
