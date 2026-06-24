# Task 9 — Test suite: integration test, coverage config, consolidation pass

**Date:** 2026-06-24

## Research / decisions

No new library research required. All dependencies (PHPUnit 12.5, brick/math, pcov)
were already resolved in Tasks 1-8 and recorded in `research-notes.md`.

### phpunit.xml.dist finalization

The existing `phpunit.xml.dist` (from Task 2) already had the bootstrap, `<source>`,
and strictness flags (`failOnDeprecation`, `failOnWarning`, etc.). Task 9 finalized it
by:

1. **Splitting into two named suites:**
   - `Unit` — includes all `tests/Shared`, `tests/Stages`, `tests/Pipeline`,
     `tests/Mcp` directories, but explicitly excludes
     `tests/Pipeline/PipelineIntegrationTest.php` so it is not double-counted.
   - `Integration` — a single `<file>` entry pointing at
     `tests/Pipeline/PipelineIntegrationTest.php`.

   Both suites run by default (no `--testsuite` flag needed for `make test`).
   Each can be run independently with `--testsuite Unit` or `--testsuite Integration`.

2. **JUnit logging** added under `<logging>` for tooling compatibility. The text
   and clover coverage outputs are supplied by the Makefile `coverage` target via
   CLI flags (`--coverage-text --coverage-clover coverage.xml`) so no change to
   the Makefile was needed.

3. **No double-hyphen XML comments** — the phpunit.xsd schema validator flags
   `--` inside XML comments; single-line comment style was used throughout.

## Integration test — what it asserts

**File:** `tests/Pipeline/PipelineIntegrationTest.php`

**Fixture:** 6 purpose-built transactions covering all pipeline paths:

| ID     | Setup                             | Expected outcome              |
|--------|-----------------------------------|-------------------------------|
| INT001 | USD, US, daytime, 1500.00         | settled, fee=3.75, net=1496.25 |
| INT002 | Missing `destination_account`     | rejected (missing-field)      |
| INT003 | amount=-50.00                     | rejected (non-positive-amount) |
| INT004 | currency=XYZ                      | rejected (invalid-currency)   |
| INT005 | overnight 02:00 + cross-border DE | rejected (high-risk, score=60) |
| INT006 | USD, US, daytime, 500.00          | settled, fee=1.25, net=498.75 |

**11 test methods, 51 assertions:**

1. `fullPipeline_sixTransactions_eachProducesExactlyOneResult` — reconciliation: 6
   inputs → 6 result files, exit code 0.
2. `happyPath_1500UsdTransaction_exactFeeAndNet` — exact string values: fee="3.75",
   net="1496.25" for INT001 (0.25% of 1500.00, half-up decimal, no float error).
3. `happyPath_500UsdTransaction_exactFeeAndNet` — fee="1.25", net="498.75" for INT006.
4. `rejectionPath_missingField_producesRejectedResult` — INT002 status=rejected, reason
   mentions "destination_account" or "missing".
5. `rejectionPath_nonPositiveAmount_producesRejectedResult` — INT003 status=rejected,
   reason mentions "amount".
6. `rejectionPath_invalidCurrency_producesRejectedResult` — INT004 status=rejected,
   reason mentions "currency" or "XYZ".
7. `rejectionPath_highRiskFraud_producesRejectedResult` — INT005 status=rejected,
   reason mentions "risk".
8. `fullPipeline_sixTransactions_correctSettledAndRejectedCounts` — 2 settled, 4 rejected.
9. `reporterSummary_afterRun_countsReconcileWithResults` — Reporter's `summarize()`
   after the run: total=6, settled=2, rejected=4; all four rejection categories
   appear in the breakdown with count=1 each.
10. `allFourRejectionPaths_reasonFieldPresentInEveryRejection` — every rejected result
    has a non-empty `reason` field (all 4 rejections verified).
11. `silentSinks_noOutputLeaksToStdout` — ob_start/ob_get_clean confirms no stdout
    output leaks during the integration run.

## Self-verification

### `make test`

```
OK (261 tests, 612 assertions)
```

Output is clean: only PHPUnit's progress dots and the OK line — no pipeline traces,
no audit log lines, no deprecation warnings. The 11 new integration tests added to
the previous 250 = 261 total.

Suite breakdown (confirmed with `--testsuite` flag):
- `--testsuite Unit`        → 250 tests, 561 assertions, OK
- `--testsuite Integration` → 11 tests, 51 assertions, OK

### `make coverage`

```
Lines:   93.49% (574/614)
Measured coverage: 93.49%
OK: coverage 93.49% meets the 80% threshold.
```

- Clover XML written to `coverage.xml` (confirmed file present).
- Line coverage 93.49% exceeds both the enforced gate (80%) and the target (90%).

### Coverage gap analysis

No gaps filled — coverage was already 93.49% before this task's integration test
(which exercises additional code paths in Integrator and Reporter). The remaining
uncovered lines (~40/614) are defensive error branches requiring filesystem fault
injection (e.g. `atomicWrite` with a failing `rename()`, or `file_put_contents`
returning false), which would need mock filesystem tricks not proportionate to
their value at 93.49% line coverage.

## Files created/changed

- **Created:** `tests/Pipeline/PipelineIntegrationTest.php`
- **Updated:** `phpunit.xml.dist` (added `<testsuite>` split, `<logging>` block)
