# Tasks 11 & 12 — `/run-pipeline` and `/validate-transactions` Skills + Dry-run CLI

**Date:** 2026-06-24

## Research / Decisions

No new library research was required. All dependencies (PHPUnit 12, brick/math,
Validator, AuditLogger, FileQueue) were already resolved in Tasks 1–8 and recorded
in `research-notes.md`.

### Architecture decisions

**ValidationReport class (`src/Pipeline/ValidationReport.php`)**

The dry-run validation logic was factored into a small, testable class rather than
kept entirely in the `bin/` entrypoint because:
1. The spec's TDD rule requires co-located unit tests for any non-trivial logic.
2. An injectable output sink (callable defaulting to `fwrite(STDOUT, ...)`) keeps
   test output clean — no pipeline output bleeds through during `make test`.
3. The class calls `Validator::process()` directly (the pure function), never `run()`,
   so no FileQueue or AuditLogger I/O is triggered. A no-op AuditLogger
   (`sink: fn() => null`) and a FileQueue pointed at `sys_get_temp_dir()` are
   injected to satisfy the Validator constructor without any file-system side effects.

**SKILL.md conventions**

Both skills mirror the frontmatter + body structure of `.claude/skills/write-spec/SKILL.md`:
- YAML frontmatter with `name` and `description`.
- Numbered procedure steps that orchestrate `make` targets (never raw PHP on the host).
- Explicit edge-case sections.

## What was created / changed

| File | Action |
|------|--------|
| `.claude/skills/run-pipeline/SKILL.md` | Created — `/run-pipeline` slash command (Task 11) |
| `.claude/skills/validate-transactions/SKILL.md` | Created — `/validate-transactions` slash command (Task 12) |
| `src/Pipeline/ValidationReport.php` | Created — testable dry-run validator helper |
| `bin/validate-transactions` | Created — CLI entrypoint (`chmod +x`); mirrors `bin/run-pipeline` |
| `tests/Pipeline/ValidationReportTest.php` | Created — 16 unit tests (PHPUnit attributes, silent sink) |

No existing files were modified.

## Self-verification

### `make test` — 277 tests, 0 deprecations, 0 failures

```
PHPUnit 12.5.30 — Runtime: PHP 8.4.22
OK (277 tests, 664 assertions)
```

16 new tests added to `ValidationReportTest`. Suite fully green with zero warnings.

### `make validate` — dry-run table output

```
Validation Results — sample-transactions.json
================================================================
Total : 8   Valid : 6   Invalid : 2

+--------+---------+-----------------------------------------------------------+
| TXN ID | Result  | Reason                                                    |
+--------+---------+-----------------------------------------------------------+
| TXN001 | valid   |                                                           |
| TXN002 | valid   |                                                           |
| TXN003 | valid   |                                                           |
| TXN004 | valid   |                                                           |
| TXN005 | valid   |                                                           |
| TXN006 | invalid | Invalid currency: 'XYZ' is not a recognised ISO 4217 code |
| TXN007 | invalid | Invalid amount: '-100.00' must be greater than zero       |
| TXN008 | valid   |                                                           |
+--------+---------+-----------------------------------------------------------+
```

TXN004 (unusual-hour + cross-border) is correctly counted valid — those rules belong
to the fraud detector, not the validator.

### Dry-run guarantee

`make clean-shared` was run to remove the prior pipeline run's `shared/` directory.
After `make validate` completed, `shared/` did not exist — confirmed with `ls`:

```
ls: cannot access 'shared/': No such file or directory
```

Nothing was written to `shared/results/` or any other subdirectory.

### `make coverage` — 93.84% (gate: ≥ 80%)

```
Lines: 93.84% (640/682)
ValidationReport: 97.06% (66/68 lines)
OK: coverage 93.84% meets the 80% threshold.
```

Coverage increased from 93.49% (pre-task) to 93.84% with the new class and tests.
