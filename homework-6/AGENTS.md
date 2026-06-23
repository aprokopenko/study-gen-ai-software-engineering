# AGENTS.md — Project Context

Single source of project context: what this system is, the concepts behind it,
the pipeline it implements, the AI agents used to build it, and the principles and
constraints every contributor must respect.

## What this project is

A **batch transaction-processing pipeline** for a banking scenario. A file of raw
transactions goes in; each transaction is validated, scored for fraud risk, and
either settled or rejected with a reason; a final outcome is recorded for every one,
plus a run summary.

It is built end-to-end by **AI agents** (described below) and is itself queryable
through a custom MCP server after a run.

## Terminology

To avoid the common confusion in this domain, we use two distinct terms:

- **Agent** — an **AI / automation workflow** that *builds* the system (writes the
  spec, generates code, writes tests, produces docs). These are the only things we
  call "agents".
- **Stage** — a **plain code component** of the pipeline that *processes* a
  transaction at runtime (validator, fraud detector, settlement). Stages contain no
  AI; they are ordinary PHP modules that cooperate by passing files.

## Core concept: the pipeline

The pipeline is a sequence of independent **stages**. Stages never call each other
directly — each reads a JSON message from one shared directory and writes its result
to the next, like a conveyor belt. This makes every hand-off inspectable on disk.

```
input/  ──validate──▶  fraud-score  ──▶  settle  ──▶  results/
                              │
                     rejected ─┴────────────────────▶  results/ (with reason)
```

### Required stages

| Stage | Responsibility |
|-------|----------------|
| **Validator** | Reject transactions missing required fields, with a non-positive amount, or an unrecognised ISO 4217 currency; pass the rest forward. |
| **Fraud detector** | Assign a risk score from documented rules (high value, unusual hour, cross-border) and flag high-risk transactions. |
| **Settlement** | Settle low-risk transactions (compute fee / net amount) and record the final outcome. |

### Shared directories and message format

```
shared/
├── input/       ← initial messages are dropped here
├── processing/  ← a stage moves a message here while working on it
├── output/      ← a stage writes its result here for the next stage
└── results/     ← final outcome (settled or rejected) lands here
```

Every file follows one envelope:

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

## The AI agents that build the system

Four agents, by role. Each produces one part of the system:

| Agent | Produces |
|-------|----------|
| **Specification agent** | The technical specification (objectives, constraints, per-stage tasks). |
| **Code-generation agent** | The pipeline stages and orchestrator. Researches its library choices via the **context7 MCP** before proposing an architecture. |
| **Unit-test agent** | The test suite and the coverage gate that blocks pushes under 80%. |
| **Documentation agent** | README (with author) and run instructions. |

The full specification lives in **`specification.md`** (produced by the Specification
agent). It is the detailed contract for the system: objectives, constraints, and a
per-agent / per-stage description of what each one builds, checks, or transforms.
Read it before generating code, tests, or docs.

## Code generation: use the `code-generator` subagent

All code generation for tasks from `specification.md` should be performed by the **`code-generator` subagent**, not ad hoc in the main session. 

- **When to use it.** Any time code, tests, config, Docker/Make tooling, the MCP server, or
  docs need to be created or changed for this project, delegate to `code-generator` rather
  than writing it inline.
- **No orchestrator.** Tasks are dispatched one by one (by you or the user); there is no
  automated loop.

## Agent working conventions

Every AI agent must leave a trace of its work in the **`./context/`** folder, so the
build process is auditable and later agents can pick up where earlier ones left off.
For each task, write a short Markdown note there capturing:

- **Investigation / research results** — what was looked up (e.g. context7 findings),
  options considered, and the decision taken with its rationale.
- **Processing summary** — what was created or changed and why.
- **Self-verification** — how the agent checked its own output (commands run, tests,
  coverage, sample results) and the outcome.

Keep `./context/` notes concise and dated; they are an audit trail, not a substitute
for the specification or the code.

## Principles and constraints

**Fixed**
- **Stack:** PHP latest stable, running in **Docker**. Everything runs inside the container —
  never invoke PHP, the package manager, or the test runner on the host.

**Open (decided by the code-generation agent, not pre-fixed)**
- Specific libraries and frameworks (money/decimal handling, MCP SDK, test config).
  The spec states *principles*, not package names; the code-gen agent resolves them
  by researching with context7.
- Resolved choices are recorded once in **`research-notes.md`** — the shared,
  cumulative library-decision ledger. Each code generation task **reads it first and reuses** prior
  decisions; context7 is consulted only for choices not yet recorded, and new findings
  are **appended** there. This keeps research from being repeated across tasks.

**Domain rules**
- **Money:** amounts are precise decimals, kept as strings — **never floating point**.
- **Currency:** must be a valid **ISO 4217** code.
- **Amounts:** non-positive amounts are rejected.
- **Audit:** every operation logs timestamp, stage, transaction ID, and outcome.
- **PII:** account numbers and names are sensitive — **never logged in plaintext**.

**Quality**
- Test coverage **≥ 80% (enforced gate)**, target **≥ 90%**.

## Beginning and ending state

- **Beginning:** `sample-transactions.json` — raw transaction records.
- **Ending:** one result file per transaction in `shared/results/`, a run summary,
  and a passing coverage gate.
