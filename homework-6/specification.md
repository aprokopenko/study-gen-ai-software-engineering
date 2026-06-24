# Banking Transaction-Processing Pipeline Specification

> Ingest the information in this file, implement the Low-Level Tasks, and generate
> the work that satisfies the High-Level and Mid-Level Objectives.

## 1. High-Level Objective

- Build a batch transaction-processing pipeline that takes a file of raw banking
  transactions, validates each one, scores it for fraud risk, and either settles it
  or rejects it with a reason — recording a final outcome for every transaction plus
  a run summary, and making the results queryable through a custom MCP server.

## 2. Mid-Level Objectives

- Every transaction in the input file produces exactly one final outcome, so nothing
  is silently dropped.
- A transaction missing any required detail is rejected with a clear reason and never
  advances further down the pipeline.
- A transaction whose amount is zero or negative is rejected with a reason.
- A transaction whose currency is not a recognised currency code is rejected with a
  reason.
- A valid transaction is assessed for fraud risk using documented, explainable rules,
  and the reasons that contributed to its risk are recorded alongside the outcome.
- A transaction judged high-risk is kept out of settlement and recorded as rejected
  with the risk reason, while lower-risk transactions continue.
- A transaction cleared of fraud risk is settled, with its fee and the resulting net
  amount recorded.
- Each processing step hands its work to the next through shared folders on disk, so
  every hand-off can be inspected after the fact.
- Each step records, for every transaction it touches, when it acted, which step it
  was, the transaction reference, and the outcome.
- Sensitive details such as account numbers and customer names are never written in
  readable form to any log.
- Monetary amounts are handled so that no cents are lost to rounding or precision
  errors at any step.
- After a run, a summary report states how many transactions were processed, settled,
  and rejected, and why rejections happened.
- After a run, anyone can ask for the status of a single transaction, a list of all
  processed transactions, or the latest run summary, without reading raw files.
- Re-running the pipeline starts from a clean slate so results from a previous run do
  not contaminate a new one.
- The behaviour of each step and of the pipeline as a whole is covered by automated
  tests, and a quality gate prevents shipping when coverage is too low.

## 3. Implementation Notes

**Fixed stack**
- PHP latest stable, running inside **Docker**. Never invoke PHP, the package manager, or
  the test runner on the host — always go through the container (e.g. `make` targets).

**Always pin to the verified latest stable release**
- For *every* part of the stack — the PHP runtime/image tag, each Composer dependency, the
  test runner, the coverage driver, the MCP SDK, and any base image — the code-gen agent
  must pin the **current latest stable release, confirmed by checking now** via context7. **Never infer a version number from training data**. Record the verified version and how it was confirmed in `research-notes.md`, and follow any resulting compatibility constraints (e.g. a newer PHP requiring a newer PHPUnit major) through to a compatible, pinned set.

**Open choices (resolved by the code-generation agent via context7, not pre-fixed)**
- Decimal/money handling library, MCP server SDK/runtime for PHP, and test/coverage
  configuration. The spec states the *principle*; the code-gen agent researches and
  records 2+ context7 queries in `research-notes.md` before fixing a package.

**Proposed project structure** — PSR-4 / Composer layout (source under `src/`, tests
mirror it, entrypoints in `bin/`, runtime message folders kept out of source). Every
Low-Level Task path below sits within this tree.

```
homework-6/
├── src/
│   ├── Shared/          ← cross-cutting infra: Envelope, FileQueue, Money, AuditLogger
│   ├── Stages/          ← pipeline stages: Validator, FraudDetector, Settlement
│   ├── Pipeline/        ← orchestration: Integrator, Reporter
│   └── Config/          ← documented constants: risk weights/cutoff, fee rate, ISO 4217 set
├── bin/                 ← CLI entrypoints: run-pipeline, validate-transactions, coverage-gate.sh
├── mcp/                 ← custom pipeline-status MCP server (server.php)
├── tests/               ← mirrors src/ (Shared/, Stages/, Pipeline/) + integration test
├── shared/              ← runtime queues: input/ processing/ output/ results/ (gitignored)
├── docs/                ← supporting documentation assets
├── context/             ← per-agent audit notes
├── .claude/
│   ├── skills/          ← /run-pipeline, /validate-transactions (SKILL.md each)
│   └── settings.json    ← coverage-gate hook (blocks push < 80%)
├── Dockerfile           ← PHP CLI image: extensions for decimal/money + coverage, Composer
├── docker-compose.yml   ← `app` service for dev/pipeline/tests (bind-mounts source + shared/)
├── Makefile             ← targets wrapping docker compose (build, run, test, coverage, mcp…)
├── .dockerignore
├── .mcp.json            ← context7 + pipeline-status servers
├── composer.json        ← deps + PSR-4 autoload + scripts
└── phpunit.xml.dist     ← test + coverage config
```

> **Container model.** All PHP/Composer/PHPUnit work goes through the `app` service
> via `make` targets. The MCP server is **not** a long-running compose service: the MCP
> client launches it on demand over stdio using the command in `.mcp.json`, so that
> command runs a **one-shot container** (`docker compose run --rm -T app …` or
> `docker run --rm -i …`) with `shared/` mounted read-only.

**Message envelope** — every file passed between steps uses one JSON shape:
```json
{
  "message_id": "uuid4-string",
  "timestamp": "ISO-8601",
  "source": "validator",
  "target": "fraud_detector",
  "type": "transaction",
  "data": { "transaction_id": "TXN001", "amount": "1500.00", "currency": "USD", "status": "validated" }
}
```

**Shared directories** (conveyor-belt hand-off; steps never call each other directly):
```
shared/
├── input/       ← initial messages dropped here
├── processing/  ← a step moves a message here while working on it
├── output/      ← a step writes its result here for the next step
└── results/     ← final outcome (settled or rejected) lands here
```

**Money**
- Amounts are precise decimals kept as **strings** — **never floating point**.
- Settlement applies a **percentage fee** (default **0.25%**) to the amount; `net =
  amount − fee`. Fee and net are recorded as strings. Rounding is half-up to the
  currency's minor unit (2 decimal places for the currencies in the sample set).

**Validation rules**
- Required fields: `transaction_id`, `timestamp`, `source_account`,
  `destination_account`, `amount`, `currency`, `transaction_type`.
- `amount` must parse as a decimal and be **strictly greater than zero**.
- `currency` must be a valid **ISO 4217** code (e.g. USD, EUR, GBP, JPY). Unknown
  codes such as `XYZ` are rejected.

**Fraud scoring (weighted additive)**
- Each triggered rule adds points to a risk score; the rules and reasons are recorded:
  - **High value** — amount ≥ a configurable threshold (default **$10,000**, applied
    per-currency face value): **+40**.
  - **Unusual hour** — transaction timestamp falls in the overnight window (default
    **00:00–05:59** local to the timestamp): **+30**.
  - **Cross-border** — `metadata.country` differs from the institution's home country
    (default home **US**): **+30**.
- A score **≥ 60** marks the transaction **high-risk**. High-risk transactions are not
  settled; they are written to `results/` as **rejected** with `reason` describing the
  contributing rules and the score. Thresholds, weights, and the cutoff are
  configurable constants documented in one place.

**Outcomes in `results/`**
- Every transaction ends as either `settled` (with fee/net) or `rejected` (with a
  `reason`). Invalid transactions and high-risk transactions both use `rejected`.

**Audit & logging**
- Every step logs: ISO-8601 timestamp, step name, transaction ID, and outcome.
- **PII** — `source_account`, `destination_account`, and any name/description fields
  are sensitive and must be masked or hashed (never plaintext) in logs.

**Run summary**
- A machine- and human-readable summary is written after each run: total processed,
  settled count, rejected count, and a breakdown of rejection reasons.

**MCP**
- Two MCP servers configured in one `.mcp.json`: **context7** (used during code
  generation) and a **custom pipeline-status server** exposing the pipeline results.
  The pipeline-status server is launched on demand over stdio inside a one-shot
  container (not a `docker compose up` service).

**Quality gate**
- Automated tests cover each step and the full pipeline path. A hook **blocks push
  when coverage < 80%**; target **≥ 90%**. Tests must not touch the real `shared/`
  directory (use a temp working area).
- The suite runs **clean**: `make test` shows only the runner's own progress/results
  (no pipeline/application output bleeding through) and **zero deprecation warnings**;
  tests use current framework idioms (PHPUnit attributes, not doc-comment metadata).

## 4. Context

### Beginning context
- `sample-transactions.json` — 8 raw transaction records (the input fixture; includes
  deliberately bad cases: unknown currency `XYZ`, negative amount, large/overnight
  amounts).
- `AGENTS.md` — single source of project context (terminology, pipeline, constraints).
- `specification.md` — current file.
- No pipeline code, tests, MCP server, or docs exist yet.

### Ending context
- `Dockerfile`, `docker-compose.yml`, and `Makefile` providing the containerised
  workflow (every command runs in the `app` service via `make` targets).
- `shared/` directory tree with one final result file per transaction in
  `shared/results/`, and a run summary report.
- Pipeline source: an orchestrator/integrator plus the three stage modules and shared
  helpers (envelope, file-queue, audit logger, money).
- `.mcp.json` (context7 + pipeline-status) and the custom MCP server source, launched
  in a one-shot container over stdio.
- `tests/` covering each stage and one full-pipeline integration test, with a passing
  coverage gate (≥ 80%, target ≥ 90%).
- `/run-pipeline` and `/validate-transactions` skills under `.claude/skills/`, and a
  coverage-gate hook in `.claude/settings.json` that blocks push below 80%.
- `README.md` (with author name, ASCII pipeline diagram, tech-stack table) and
  `HOWTORUN.md`.
- `./context/` audit notes left by each building agent.

## 5. Low-Level Tasks

> One component per heading, in execution order. Each stage reads from one shared
> directory and writes to the next; none calls another directly. File paths follow the
> proposed project structure above.
>
> **Tests are co-located (TDD).** Every task that produces testable source writes its
> own unit tests in the same task, mirroring the source under `tests/`, covering the
> happy path and the edge cases that task lists. Coverage accrues incrementally as tasks
> land. Task 9 is the exception: it adds only the full-pipeline integration test and the
> PHPUnit/coverage configuration — it does not re-author per-component unit tests.

### 1. Docker environment & developer tooling

- **File(s):** `Dockerfile`, `docker-compose.yml`, `Makefile` (already exists — extend it), `.dockerignore`
- **Function/Unit:** `make` targets — `build`, `install`, `run`, `validate`, `test`, `coverage`, `mcp`, `shell` (plus the existing `clean-shared`, `clean`, `reset`)
- **Prompt:** Create the containerised environment everything else runs in. Write a `Dockerfile` on a PHP CLI base with the extensions needed for decimal/money handling and code coverage, plus Composer. Add a `docker-compose.yml` defining an `app` service that bind-mounts the source and `shared/` and is used for interactive dev, running the pipeline, and tests. The repo already ships a `Makefile` with cleanup targets (`clean-shared`, `clean`, `reset`) — **extend it, do not overwrite it**, adding targets that wrap `docker compose` so nobody runs PHP/Composer/PHPUnit on the host: `build`, `install`, `run` (pipeline), `validate` (validator dry-run), `test`, `coverage` (enforcing the gate threshold), `mcp` (one-shot MCP server container), and `shell`. Resolve the base image and coverage-extension choice (pcov vs. xdebug) via context7 first.
- **Details:** This is the prerequisite for every later task — all of them execute through these targets. Coverage requires pcov or xdebug enabled in the image. Bind-mount `shared/` so results stay inspectable on the host. **Run the container as the host user, not root**, so files written to the bind-mounted `shared/` (and other generated paths) are owned by the host user and remain deletable without `sudo` — set `user: "${UID:-1000}:${GID:-1000}"` on the `app` service and have the Makefile export `UID`/`GID` (`export UID := $(shell id -u)` / `GID := $(shell id -g)`) so `docker compose run` picks them up. The MCP server is **not** a long-running compose service: provide a one-shot invocation (`docker compose run --rm -T app php mcp/server.php`, no TTY so stdio works) for the MCP client to call from `.mcp.json` (see Task 8). Edge cases: keep the image lean (`.dockerignore` excludes `vendor/`, `shared/`, `docs/`); ensure the coverage target exits non-zero when below the threshold; the running user must still be able to write `shared/` and read the mounted source.

### 2. Shared infrastructure (envelope, file queue, money, audit logger)

- **File(s):** `src/Shared/Envelope.php`, `src/Shared/FileQueue.php`, `src/Shared/Money.php`, `src/Shared/AuditLogger.php`
- **Function/Unit:** `Envelope::create()` / `::fromJson()`; `FileQueue::move()` / `::read()` / `::write()`; `Money::parse()` / `::fee()` / `::subtract()` / `::round()`; `AuditLogger::log(step, transactionId, outcome, context)`
- **Prompt:** Implement the shared helpers every stage depends on: an envelope type that builds and parses the standard JSON envelope (uuid4 `message_id`, ISO-8601 timestamp, source, target, type, data); a file-queue helper that atomically moves a message between `shared/input`, `processing`, `output`, and `results`; a money helper that parses, multiplies, subtracts, and rounds decimal amounts held as strings (never float), half-up to the minor unit; and a PII-safe audit logger that records timestamp + step + transaction_id + outcome while masking account/name fields. Resolve the decimal library via context7 first and record the queries in `research-notes.md`.
- **Details:** Atomic, inspectable file hand-offs; strings for all money; the logger must never emit account numbers or names in plaintext. Handle malformed JSON and missing files defensively.

### 3. Validator stage

- **File(s):** `src/Stages/Validator.php`
- **Function/Unit:** `Validator::process(array $message): array`
- **Prompt:** Implement the validator stage. Read each transaction message from `shared/input`, move it to `processing` while working, then either pass it forward as `status=validated` via `shared/output`, or reject it to `shared/results` with `status=rejected` and a `reason`. Reject when a required field is missing, when the amount is not a positive decimal, or when the currency is not a valid ISO 4217 code. Log every decision via the audit logger. Resolve the currency iso library via context7 first and record the queries in research-notes.md.
- **Details:** Required fields = `transaction_id`, `timestamp`, `source_account`, `destination_account`, `amount`, `currency`, `transaction_type`. Amount must be > 0. Currency must be a known ISO 4217 code (reject `XYZ`). Edge cases: amount `"-100.00"`, non-numeric amount, empty/missing fields.

### 4. Fraud detector stage

- **File(s):** `src/Stages/FraudDetector.php` (rules/constants in `src/Config/`)
- **Function/Unit:** `FraudDetector::process(array $message): array`
- **Prompt:** Implement the fraud detector stage. Read validated messages from the hand-off directory, compute a weighted additive risk score, and record the contributing reasons. Apply high value (amount ≥ threshold) +40, unusual hour (overnight window) +30, and cross-border (country ≠ home country) +30. If the score reaches the cutoff, mark the transaction high-risk and write it to `shared/results` as `rejected` with a reason listing the triggered rules and the score; otherwise pass it forward to settlement. Keep all thresholds, weights, and the cutoff as documented constants. Log every decision.
- **Details:** Defaults: high-value $10,000, overnight 00:00–05:59, home country US, cutoff 60. Compare amounts via the Money helper (no float). Edge cases: exactly-at-threshold amount, exactly-at-cutoff score, missing `metadata.country` (treat as unknown → cross-border if it cannot be confirmed as home).

### 5. Settlement stage

- **File(s):** `src/Stages/Settlement.php`
- **Function/Unit:** `Settlement::process(array $message): array`
- **Prompt:** Implement the settlement stage. Read low-risk messages forwarded by the fraud detector, compute a percentage fee (default 0.25%) on the amount, derive `net = amount − fee` using the Money helper with half-up rounding, and write the final outcome to `shared/results` with `status=settled`, including fee and net as strings. Log every settlement.
- **Details:** Fee rate is a documented constant. All monetary values are strings; round to the currency minor unit. Edge cases: very large amounts, fee rounding boundaries.

### 6. Orchestrator / integrator

- **File(s):** `src/Pipeline/Integrator.php`, entrypoint `bin/run-pipeline`
- **Function/Unit:** `Integrator::run(string $inputFile): int`
- **Prompt:** Implement the integrator that runs the pipeline end-to-end: create the `shared/` directory tree if missing, clear prior run state, load `sample-transactions.json`, wrap each record in the standard envelope and drop it in `shared/input`, then run the stages in order (validator → fraud detector → settlement) so every transaction reaches `shared/results`. Emit a progress trace through an **injectable output sink** (a callable/stream injected via the constructor, defaulting to stdout) — never hard-code `echo`/`print` in the run logic — so the CLI prints normally while tests pass a silent or capturing sink. Exit non-zero if any transaction fails to reach a final outcome.
- **Details:** Idempotent setup; clean start each run. Must guarantee one result file per input transaction. The progress trace must be fully silenceable in tests (no pipeline output during `make test`). Edge cases: empty input file, malformed JSON record.

### 7. Run summary reporter

- **File(s):** `src/Pipeline/Reporter.php` (writes `shared/results/summary.json` + `summary.txt`)
- **Function/Unit:** `Reporter::summarize(): array`
- **Prompt:** Implement a reporter that reads `shared/results` after a run and writes a summary: total processed, settled count, rejected count, and a breakdown of rejection reasons. Produce both a human-readable text summary and a structured form the MCP server can serve.
- **Details:** Counts must reconcile with the number of input transactions. Edge cases: zero results, all-rejected runs.

### 8. Custom MCP server (pipeline-status)

- **File(s):** `mcp/server.php`, `.mcp.json`
- **Function/Unit:** tools `get_transaction_status`, `list_pipeline_results`; resource `pipeline://summary`
- **Prompt:** Build a custom MCP server (PHP runtime) that makes the pipeline queryable over stdio. Expose `get_transaction_status(transaction_id)` returning the current status from `shared/results`, `list_pipeline_results()` returning a summary of all processed transactions, and a `pipeline://summary` resource returning the latest run summary as text. In `.mcp.json`, register two servers: `context7` and `pipeline-status`.
- **SDK & transport:** Prefer **official PHP MCP SDK** over other libs
- **Launch (`.mcp.json`):** Launch the server in a **one-shot container over stdio with interactive stdin**: `docker run -i --rm <image> php mcp/server.php`, mounting `shared/` (read-only is fine). Reuse the project's app image if practical; otherwise a small dedicated MCP image is acceptable.
- **Details:** stdio transport — the server must write nothing but protocol frames to stdout (send logs to stderr/file). Read-only over `shared/results` and the summary. Launched on demand by the MCP client. Edge cases: unknown `transaction_id`, no run yet (empty results), stray stdout breaking the JSON-RPC stream.
- **Verification (bounded — do NOT thrash):** The acceptance bar is the **unit tests on the transport-agnostic data-access class** (known/unknown txn, list, summary, empty results) passing. Then run **one** smoke test piping the full handshake into the container: line 1 `initialize`, line 2 `notifications/initialized`, line 3 a `tools/call`. A single clean response is sufficient proof. If the live smoke test does not round-trip in ~2 attempts, rely on the unit tests, record the limitation in the context note, and stop — do not keep retrying transport/Docker variations.

### 9. Test suite (integration + coverage config; unit tests are co-located)

> **Per-component unit tests are NOT written here.** Under this project's TDD workflow
> each implementation task (Tasks 2–8, 11–12) ships its own unit tests alongside the
> code it produces (`tests/Shared/*`, `tests/Stages/*`, etc.). Task 9 owns only the
> cross-cutting pieces: the full-pipeline integration test, the PHPUnit/coverage
> configuration, and a final consolidation pass. Do not re-write or duplicate the
> per-component unit tests already created by earlier tasks.

- **File(s):** `tests/Pipeline/PipelineIntegrationTest.php`, `phpunit.xml.dist`
- **Function/Unit:** Integrator full-path integration test; PHPUnit + coverage config
- **Prompt:** Add the one full-pipeline integration test that runs the integrator end-to-end on a fixture, and finalise the `phpunit.xml.dist` (test suites + coverage reporting) so an overall percentage is emitted in a machine-readable form. Confirm the suite as a whole (the co-located unit tests from earlier tasks plus this integration test) runs green and meets the coverage target through the Docker `make` targets from Task 1. As the consolidation owner, also verify the suite is **clean**: `make test` shows only the runner's progress/results with **no pipeline or application output bleeding through**, and reports **zero deprecations**. If any earlier task left a gap in its own unit coverage, note it and have that gap filled in its component test file rather than adding a parallel test here.
- **Details:** The integration test asserts one result per input and correct fee/net math across the happy path and every rejection reason (missing field, non-positive amount, bad currency, high-risk) end-to-end. Isolate from the real `shared/` directory using a temp working area. Target ≥ 90% overall coverage; the enforced gate lives in Task 10. Emit the overall coverage percentage where the gate hook can read it (e.g. a clover/text report). Coverage accrues incrementally — Task 9 verifies the total, it does not author the bulk of the tests.

### 10. Coverage-gate hook (blocks push < 80%)

- **File(s):** `.claude/settings.json` (hook config), supporting script e.g. `bin/coverage-gate.sh` and/or `.githooks/pre-push`
- **Function/Unit:** pre-push hook that runs `make coverage` and enforces the threshold
- **Prompt:** Add the coverage gate required by the assignment: a hook that runs the test suite with coverage in the container (`make coverage` from Task 1), parses the overall percentage, and **blocks the push** with a non-zero exit when it is below 80%. Wire it in `.claude/settings.json` (and/or a git `pre-push` hook the repo installs). Print the measured coverage and the threshold so the failure is self-explanatory.
- **Details:** Must **fail**, not merely warn. Threshold 80% (target 90%). Reuses the Task 9 suite and the Task 1 `coverage` target. Edge cases: missing/unreadable coverage report → treat as failure; coverage exactly at 80% → allow (gate is `< 80%`).

### 11. `/run-pipeline` skill (slash command)

- **File(s):** `.claude/skills/run-pipeline/SKILL.md`
- **Function/Unit:** slash command `/run-pipeline`
- **Prompt:** Create a slash command that runs the full pipeline end-to-end and reports the outcome. Steps: (1) check `sample-transactions.json` exists; (2) clear the `shared/` directories; (3) run the pipeline via `make run` (Docker); (4) summarise the results in `shared/results/`; (5) report any transactions that were rejected and why.
- **Details:** Orchestrates the Docker `make` targets rather than calling PHP directly. Read-only summary of `shared/results/`. Edge cases: missing input file (stop with a clear message), empty results after a run.

### 12. `/validate-transactions` skill (slash command)

- **File(s):** `.claude/skills/validate-transactions/SKILL.md`
- **Function/Unit:** slash command `/validate-transactions`
- **Prompt:** Create a slash command that validates every transaction in `sample-transactions.json` **without** running the full pipeline. Steps: (1) run the validator in dry-run mode via `make validate` (Docker); (2) report total count, valid count, invalid count, and the reason for each rejection; (3) show the results as a table.
- **Details:** Dry-run only — no fraud scoring, no settlement, nothing written to `shared/results/`. Relies on the validator's dry-run path and the Task 1 `validate` target. Edge cases: all-valid set, all-invalid set.

### 13. Documentation (README & run guide)

- **File(s):** `README.md`, `HOWTORUN.md`
- **Function/Unit:** project documentation (Agent 4 deliverable)
- **Prompt:** Write the project documentation. `README.md` must include the author's name (the repo owner, e.g. "Created by <name>"), a 1–2 paragraph description of what the system does, one bullet per pipeline stage describing its responsibility (validator, fraud detector, settlement, reporter), an ASCII architecture diagram of the `input → validate → fraud-score → settle → results` flow, and a tech-stack table. `HOWTORUN.md` must give numbered steps from setup to demo using the Docker `make` targets (`build`, `install`, `run`, `test`, `coverage`, `mcp`), and show how to invoke the `/run-pipeline` and `/validate-transactions` skills and the MCP server.
- **Details:** Author name is required (assignment gate). Keep everything consistent with the actual `make` targets and file layout, and host-free (all commands go through Docker). Edge cases: diagram must show the rejected-path branch (invalid + high-risk → `results/`), not just the happy path.
