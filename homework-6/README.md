# Banking Transaction-Processing Pipeline

**Created by Alex Prokopenko**

A batch transaction-processing pipeline for a banking scenario. A file of raw
transactions is read in; each transaction is validated, scored for fraud risk, and
either settled or rejected with a reason. A final outcome is recorded for every
transaction, plus a human-readable run summary. After a run, the pipeline results
are queryable through a custom MCP server.

The system is built entirely in PHP 8.4, runs inside Docker (host-free), and is
covered by a PHPUnit test suite with a ≥ 80% coverage gate enforced at push time.
All inter-stage communication happens through files in `shared/` — no stage ever
calls another directly, making every hand-off inspectable on disk.

---

## Pipeline stages

- **Validator** — Checks each transaction for required fields, a positive amount,
  and a recognised ISO 4217 currency code. Transactions that fail any rule are
  rejected immediately with a descriptive reason; the rest are passed forward.

- **Fraud Detector** — Assigns a weighted additive risk score to each validated
  transaction: +40 for high-value (≥ $10,000), +30 for an unusual hour (00:00–05:59
  UTC), +30 for cross-border (country ≠ US home). A score ≥ 60 marks the transaction
  as high-risk and rejects it. Low-risk transactions continue to settlement.

- **Settlement** — Computes a 0.25% processing fee and the net amount (amount −
  fee, rounded half-up to 2 decimal places, stored as strings — never floats).
  Writes the final `status: settled` record with fee and net.

- **Reporter** — Reads all result files from `shared/results/`, totals the counts,
  groups rejection reasons into canonical categories, and writes
  `shared/results/summary.json` and `shared/results/summary.txt`.

---

## Architecture

```
sample-transactions.json
        |
        v
  [ Validator ] ──── invalid (missing field / non-positive amount / bad currency) ─────┐
        |                                                                               |
        v                                                                               |
 [ Fraud Detector ] ── high-risk (score >= 60) ────────────────────────────────────────┤
        |                                                                               |
        v                                                                               v
  [ Settlement ]                                                               shared/results/
   fee = 0.25%                                                                 (status: rejected
   net = amount - fee                                                           + reason)
        |
        v
 shared/results/
 (status: settled
  + fee + net)
        |
        v
   [ Reporter ]
  summary.json
  summary.txt
```

The shared directory layout during a run:

```
shared/
├── input/       <- initial messages dropped here by the orchestrator
├── processing/  <- a stage moves a message here while working on it
├── output/      <- a stage writes its result here for the next stage
└── results/     <- final outcomes (settled + rejected) + summary files
```

Every inter-stage message follows one envelope schema:

```json
{
  "message_id": "uuid4-string",
  "timestamp": "ISO-8601",
  "source": "validator",
  "target": "fraud_detector",
  "type": "transaction",
  "data": {
    "transaction_id": "TXN001",
    "amount": "1500.00",
    "currency": "USD",
    "status": "validated"
  }
}
```

---

## Tech stack

| Component         | Choice / Version                              |
|-------------------|-----------------------------------------------|
| PHP runtime       | PHP 8.4 (`php:8.4-cli` official Docker image) |
| Container runtime | Docker + Docker Compose                       |
| Dependency manager| Composer 2                                    |
| Test framework    | PHPUnit `^12.5`                               |
| Coverage driver   | pcov (installed via `pecl install pcov`)      |
| Decimal arithmetic| `brick/math ^0.12` (`BigDecimal` + `HALF_UP`) |
| MCP server SDK    | `mcp/sdk ^0.6.0` (official PHP MCP SDK)       |
| Currency validation| `src/Config/Iso4217.php` — constant set of ~170 active ISO 4217 codes |

---

## Sample run (8 transactions)

| Transaction | Outcome  | Detail                                      |
|-------------|----------|---------------------------------------------|
| TXN001      | settled  | fee 3.75, net 1496.25                       |
| TXN002      | settled  |                                             |
| TXN003      | settled  |                                             |
| TXN004      | rejected | high-risk (fraud score 60)                  |
| TXN005      | settled  |                                             |
| TXN006      | rejected | invalid currency XYZ                        |
| TXN007      | rejected | non-positive amount -100.00                 |
| TXN008      | settled  |                                             |

5 settled, 3 rejected.
