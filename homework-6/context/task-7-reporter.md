# Task 7 — Run summary reporter
Date: 2026-06-23

## Research / decisions

### Library research
No new library needed. All dependencies already resolved in research-notes.md:
- `brick/math` (Task 2) for Money — not needed here; Reporter reads final string values from result files.
- `BankingPipeline\Shared\Envelope::fromJson()` (Task 2) used to parse result files.
- PHPUnit 12.5 (Task 1) with PHP attributes for tests.

### Reason grouping scheme

Raw rejection reasons from the pipeline are normalised into four canonical categories:

| Category            | Source stage    | Trigger patterns (case-insensitive)                          |
|---------------------|-----------------|--------------------------------------------------------------|
| `missing-field`     | Validator        | contains "missing" OR "required field"                       |
| `non-positive-amount` | Validator      | contains "amount" AND ("zero" OR "positive" OR "greater than") |
| `invalid-currency`  | Validator        | contains "currency" OR "iso 4217"                            |
| `high-risk`         | Fraud Detector   | contains "high-risk" OR "high risk" OR ("risk" AND "score")  |
| `unknown`           | fallback         | anything not matching above                                  |

Patterns are applied in that order; more-specific checks first. This yields a meaningful four-bucket breakdown rather than one-per-unique-string.

### Self-summary-file exclusion
`summary.json` and `summary.txt` are listed in a `SUMMARY_FILES` constant. Any file whose basename matches this list is skipped during glob scan so re-summarising an already-summarised results dir does not double-count.

### Malformed file handling
Files that cannot be read (`file_get_contents` returns false) or that produce a parse exception from `Envelope::fromJson()` are skipped and their filenames are appended to the `errors` array returned in the summary. This avoids silent miscounting and lets callers detect partial data without crashing.

### Zero-results / missing dir
If the results directory does not exist, the reporter treats it as zero results (no crash) and creates the directory when writing the summary files.

### Injectable results dir
`Reporter` accepts `$resultsDir` as a constructor parameter so tests can point it at a temp dir without touching the real `shared/results/`.

### Decision on wiring into bin/run-pipeline
Not wired — Task 7 is scoped to the reporter class only. The CLI entrypoint (`bin/run-pipeline`) is owned by Task 6 and is left unchanged to stay within scope.

## Files created

- `src/Pipeline/Reporter.php` — Reporter class with `summarize(): array`
- `tests/Pipeline/ReporterTest.php` — 22 unit tests (all edge cases listed in spec)

## Self-verification

### Commands run

```
make test
```

**Outcome:** 236 tests, 519 assertions — all green. Zero deprecation warnings. No pipeline/application output bleeding through.

Previously 214 tests; 22 new tests added for Task 7 (delta: 22 tests, 100 new assertions in the new Reporter tests).

### 8-sample run summary (via `docker compose run`)

```
Total processed : 8
Settled         : 5
Rejected        : 3

Rejection breakdown:
  high-risk:               1
  invalid-currency:        1
  non-positive-amount:     1
```

- TXN004 → high-risk (unusual_hour + cross_border, score=60)
- TXN006 → invalid-currency (XYZ)
- TXN007 → non-positive-amount (-100.00)
- TXN001, TXN002, TXN003, TXN005, TXN008 → settled

Reconciliation: 5 + 3 = 8 ✓

Both `shared/results/summary.json` and `shared/results/summary.txt` written successfully.
