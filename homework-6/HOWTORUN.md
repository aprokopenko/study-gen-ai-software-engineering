# How to Run — Banking Transaction-Processing Pipeline

Everything runs inside Docker. Do not invoke `php`, `composer`, or `phpunit` on the
host. All commands below go through the provided `make` targets.

---

## Prerequisites

- Docker and Docker Compose installed on the host.
- The repository cloned locally.

---

## Setup

### 1. Build the Docker image

```bash
make build
```

Builds the `homework-6-app` image from the project `Dockerfile` (PHP 8.4-cli with
bcmath, gmp, and pcov installed).

### 2. Install Composer dependencies

```bash
make install
```

Runs `composer install` inside the container and writes `vendor/` to the project
directory. Required before any other step.

### 3. (Optional) Enable the coverage gate

```bash
make install-hooks
```

Sets the git `core.hooksPath` to `.githooks/` so the pre-push hook runs
`bin/coverage-gate.sh` before every push. The gate blocks pushes when line
coverage falls below 80%. Run once per clone.

---

## Running the pipeline

### 4. Full pipeline run

```bash
make run
```

Processes all transactions in `sample-transactions.json` end-to-end:
validate → fraud-score → settle/reject → report. Results land in `shared/results/`
(one JSON file per transaction, plus `summary.json` and `summary.txt`).

Between runs, clear the shared queues:

```bash
make clean-shared
```

### 5. Dry-run: validator only

```bash
make validate
```

Runs only the validator stage against `sample-transactions.json`. Nothing is written
to `shared/` — no fraud scoring, no settlement, no results directory changes.
Useful for quickly checking which records pass or fail validation rules.

---

## Tests and coverage

### 6. Run the test suite

```bash
make test
```

Runs the full PHPUnit suite (no coverage driver — fast). All tests must pass before
pushing.

### 7. Run with coverage measurement

```bash
make coverage
```

Runs PHPUnit with pcov enabled. Prints a coverage report to the terminal and writes
`coverage.xml`. Exits non-zero if line coverage falls below the 80% threshold
(currently ~93–94%).

---

## MCP server (pipeline-status)

The `pipeline-status` MCP server lets an MCP-capable client (e.g. Claude Code) query
pipeline results after a run, without reading raw JSON files directly.

### 8. One-shot manual launch

```bash
make mcp
```

Starts the server in a one-shot container over stdio. Useful for smoke-testing the
server or sending raw JSON-RPC frames manually. Exit with Ctrl-C.

### Automatic launch via Claude Code

The `.mcp.json` file at the project root registers `pipeline-status` as an MCP
server entry. Claude Code launches it automatically on demand using:

```
docker run -i --rm -w /app \
  -v "$PWD:/app" \
  -v "$PWD/shared:/app/shared:ro" \
  homework-6-app \
  php mcp/server.php
```

No TTY (`-i` only, not `-t`) keeps the JSON-RPC byte stream clean.

### Tools and resources exposed

| Name                    | Type     | Description                                                                  |
|-------------------------|----------|------------------------------------------------------------------------------|
| `get_transaction_status`| Tool     | Look up a single transaction by ID — returns status, fee/net or reject reason|
| `list_pipeline_results` | Tool     | List all processed transactions from the last run (ID + status for each)     |
| `pipeline://summary`    | Resource | Full text of the latest run summary (`summary.txt`)                          |

Example usage in Claude Code after a `make run`:

```
> What is the status of TXN004?
(Claude calls get_transaction_status("TXN004") → "rejected: high-risk score 60")
```

---

## Claude Code skills (slash commands)

Two slash commands are available when working in Claude Code inside this project.

### `/run-pipeline`

Runs the full pipeline end-to-end and reports the outcome:

```
/run-pipeline
```

Equivalent to `make clean-shared && make run`, but surfaces the summary in Claude's
reply.

### `/validate-transactions`

Validates every transaction in `sample-transactions.json` without running the full
pipeline:

```
/validate-transactions
```

Reports total count, valid count, invalid count, and the reason for each rejection
as a table. Nothing is written to `shared/`.

---

## Quick-reference: all make targets

| Target          | Description                                                              |
|-----------------|--------------------------------------------------------------------------|
| `make build`    | Build (or rebuild) the Docker image                                      |
| `make install`  | Install Composer dependencies inside the container                       |
| `make install-hooks` | Enable the git pre-push coverage gate (run once per clone)          |
| `make run`      | Run the full transaction-processing pipeline (end-to-end)                |
| `make validate` | Run the validator stage only (dry-run — no fraud scoring or settlement)  |
| `make test`     | Run the PHPUnit test suite (no coverage driver — fast)                   |
| `make coverage` | Run tests with pcov coverage; exits non-zero if below 80%                |
| `make mcp`      | Launch the pipeline-status MCP server in a one-shot container (stdio)    |
| `make shell`    | Open an interactive shell inside the app container                       |
| `make clean-shared` | Empty the runtime message queues (shared/) between pipeline runs     |
| `make clean`    | Remove dependencies and caches (vendor/, phpunit cache)                  |
| `make reset`    | DESTRUCTIVE: delete all generated code and artifacts for a fresh start   |
