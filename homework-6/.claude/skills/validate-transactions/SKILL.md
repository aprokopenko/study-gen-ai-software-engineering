---
name: validate-transactions
description: >-
  Validate every transaction in sample-transactions.json without running the
  full pipeline. Use when the user says "validate transactions", "dry-run
  validation", "validate-transactions", or wants to check which records pass
  or fail the validation rules (required fields, positive amount, ISO 4217
  currency) without fraud scoring, settlement, or writing to shared/results/.
---

# Validate Transactions

Run the validator stage in dry-run mode via Docker and report the validation
outcome for every transaction as a table — no fraud scoring, no settlement,
and nothing written to `shared/results/`.

## Prerequisites

- Docker must be running and the image built (`make build`).
- Composer dependencies installed (`make install`).

## Procedure

1. **Run the dry-run validator via Docker.** Never call PHP directly on the
   host — always go through the `make` target:

   ```
   make validate
   ```

   This executes `php bin/validate-transactions` inside the container.
   The validator applies only the three validation rules (required fields,
   positive amount, ISO 4217 currency) to every transaction in
   `sample-transactions.json`. It does **not** run fraud scoring or
   settlement, and it writes **nothing** to `shared/` or `shared/results/`.

2. **Read the printed report.** The command prints a validation table to
   stdout with one row per transaction, showing its ID, validation result
   (`valid` / `invalid`), and the rejection reason when applicable.

3. **Present the results to the user** using the counts and table from the
   command output:

   - Total transactions checked
   - Valid count
   - Invalid count
   - A table: Transaction ID | Result | Reason

## Reporting format

```
Validation Results — sample-transactions.json
=============================================
Total : 8   Valid : 6   Invalid : 2

+--------+---------+-----------------------------------------------------+
| TXN ID | Result  | Reason                                              |
+--------+---------+-----------------------------------------------------+
| TXN001 | valid   |                                                     |
| TXN006 | invalid | Invalid currency: 'XYZ' is not a recognised ...    |
| TXN007 | invalid | Invalid amount: '-100.00' must be greater than zero |
+--------+---------+-----------------------------------------------------+
```

## Edge cases

- **All-valid set** — all rows show `valid` with an empty Reason column;
  Invalid count is 0. This is normal — report it as a clean result.
- **All-invalid set** — all rows show `invalid`; Valid count is 0. Report
  every rejection reason in the table.
- **Dry-run guarantee** — confirm to the user that `make validate` writes
  nothing to `shared/results/` (the directory is untouched). If the user
  is unsure, they can verify with `ls shared/results/` before and after.
