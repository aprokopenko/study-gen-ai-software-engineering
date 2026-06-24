---
name: run-pipeline
description: >-
  Run the full banking transaction-processing pipeline end-to-end and report the
  outcome. Use when the user says "run the pipeline", "process transactions",
  "run-pipeline", or wants to execute all stages (validate, fraud-score, settle)
  against sample-transactions.json and see the final results.
---

# Run Pipeline

Execute the full pipeline end-to-end through Docker and summarise every
transaction outcome — settled counts, rejected counts, and the reason for
each rejection.

## Prerequisites

- Docker must be running and the image built (`make build`).
- Composer dependencies installed (`make install`).

## Procedure

1. **Verify the input file exists.** Check that `sample-transactions.json` is
   present in the project root. If it is missing, stop immediately and tell the
   user — the pipeline has nothing to process.

   ```
   ls sample-transactions.json
   ```

2. **Clear prior run state.** Remove any leftover files from the previous run so
   results do not bleed across runs:

   ```
   make clean-shared
   ```

3. **Run the full pipeline via Docker.** Never call PHP directly on the host —
   always go through the `make` target:

   ```
   make run
   ```

   The integrator (Validator → Fraud Detector → Settlement → Reporter) runs
   inside the container. Every transaction in `sample-transactions.json`
   produces exactly one result file in `shared/results/`.

4. **Read the run summary.** After `make run` completes, read the human-readable
   summary written to `shared/results/summary.txt` and the structured form in
   `shared/results/summary.json`:

   ```
   cat shared/results/summary.txt
   ```

5. **Report rejected transactions and reasons.** For each `.json` file in
   `shared/results/` whose `data.status` is `"rejected"`, extract
   `data.transaction_id` and `data.reason` and present them clearly. You can
   read individual result files or rely on `summary.json`'s
   `rejection_breakdown` map for grouped counts.

## Reporting format

Present a concise summary to the user covering:

- Total transactions processed
- Settled count / Rejected count
- A table of rejected transactions with transaction ID and reason
- Any processing errors surfaced in `summary.json` → `errors`

## Edge cases

- **Missing `sample-transactions.json`** — stop at step 1 with a clear error
  message; do not proceed to `make clean-shared` or `make run`.
- **Empty results after a run** — if `shared/results/` contains no `.json`
  files (other than the summary) after `make run` exits, report that no
  transactions were processed and suggest checking the input file for valid
  JSON records.
- **Non-zero exit from `make run`** — capture the exit code; if it is non-zero
  report the failure and show any stderr output before summarising partial
  results.
