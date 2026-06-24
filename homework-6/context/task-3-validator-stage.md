# Task 3 — Validator Stage (2026-06-23)

## Research / decisions

### ISO 4217 currency validation approach

**Query 1 (context7):** `iso4217` → `/dahlia/iso4217` (Python only — not applicable to PHP)

**Query 2 (context7):** `brick/money` → `/brick/money` (High reputation, 141 snippets, benchmark 80.6)
- `Currency::of('USD')` performs an ISO 4217 lookup; unknown codes throw `UnknownCurrencyException`.
- `IsoCurrencyProvider::getInstance()->getAvailableCurrencies()` returns ~170+ active codes.
- This library was already assessed in Task 2 research-notes as "overkill for arithmetic-only work".

**Decision: hardcoded constant set in `src/Config/Iso4217.php`**

Rationale:
1. The spec explicitly anticipates "an ISO 4217 set living in Config" as a valid option.
2. `brick/math` (already installed) handles arithmetic; `brick/money` adds currency objects and
   formatting not needed for validation-only work.
3. A PHP constant array (`array<string, true>`) with `isset()` lookup is zero-runtime-cost,
   fully inspectable in the codebase, and has no external dependency surface.
4. The ISO 4217 active-currency list changes rarely; updates are a trivial one-line edit.
5. `brick/money` was already in research-notes as considered/rejected for Task 2; same reasoning applies.

No new Composer dependency required — `make install` not needed for this task.

## What was created / changed

| File | Action | Description |
|------|--------|-------------|
| `src/Config/Iso4217.php` | Created | Full set of ~170 active ISO 4217 alphabetic currency codes as `const CODES = [...]` with `isValid(string): bool` helper |
| `src/Stages/Validator.php` | Created | `Validator::process(array): array` — pure validation logic; `run()` — orchestrates FileQueue I/O |
| `tests/Stages/ValidatorTest.php` | Created | 46 tests covering happy path and all spec edge cases |
| `research-notes.md` | Appended | Task 3 ISO 4217 currency-library decision recorded |

### Design choices in `Validator`

- **`process(array $message): array`** is a pure-ish method (no file I/O) — directly testable
  and reusable by Task 12 dry-run path.
- **`run()`** orchestrates the FileQueue: read from input/, move to processing/ while working,
  write result to output/ (validated) or results/ (rejected), delete from processing/ on done.
- `baseDir` injected at construction so the validator can unlink the processing copy without
  a `delete()` method on FileQueue (which doesn't expose one).
- Rejection order: missing fields first, then amount, then currency — first failure short-circuits.
- AuditLogger `context` only includes non-PII fields (`amount`, `currency`, `reason`).
  `source_account` and `destination_account` never enter the log context.

## Self-verification

### Command run
```
make test
```

### Outcome
```
PHPUnit 12.5.30 by Sebastian Bergmann and contributors.
Runtime: PHP 8.4.22
...............................................................  63 / 126 ( 50%)
............................................................... 126 / 126 (100%)
OK (126 tests, 268 assertions)
```

- 126 tests total (was 80 before Task 3; 46 new tests in ValidatorTest).
- Zero deprecation warnings (`failOnDeprecation="true"` in phpunit.xml.dist).
- No pipeline/application output bleeding through — only PHPUnit progress.
- All tests use temp dirs; real `shared/` is never touched.
