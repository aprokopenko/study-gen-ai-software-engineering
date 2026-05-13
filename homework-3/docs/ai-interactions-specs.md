# AI Interaction Log — Homework 3 Specification

**Model**: Claude Opus 4.7 (`claude-opus-4-7`)
**Tool / IDE**: Claude Code via the VS Code extension

## Goal of the session

Produce the specification package for Homework 3 (specification-driven design). Domain: **spending-caps application** — user sets monthly budget limits (overall or per category) on transactions aggregated from connected bank accounts and cards. No code, only documents.

## Interaction flow

### 1. Framing & scope decisions

The session began with the homework brief (`TASKS.md`) and the supplied template. Multiple focused Q&A rounds locked v1 scope: notify-only enforcement (no real-time blocking); transactions sourced from bank accounts + cards via a mocked aggregator behind a stable Adapter contract; MCC-based auto-categorisation with per-transaction user override; monthly periods; single base currency with FX on ingest; push-only notifications (email proposed early, dropped later); fixed 50 / 80 / 100 % thresholds. Product named **PennyBrake**.

### 2. Specification structure

Settled on a layered structure aligned with the template plus the homework's mandatory cross-cutting requirements (edge cases, verification, performance):
`1. High-Level Objective → 2. Scope & Stakeholders → 3. Mid-Level Objectives → 4. Functional Flows → 5. Implementation Notes → 6. Context → 7. Low-Level Tasks`.

A pivotal style decision was made mid-session: **mid-level objectives must be stakeholder-readable** — features and policies in plain language a BA could review — not engineer-jargon. Numbers, schemas, and mechanics were pushed to Implementation Notes. This was saved as a feedback memory for future sessions.

### 3. Drafting sections iteratively

Each section was drafted into the file, reviewed, corrected, then locked:

- **§3 Mid-Level Objectives** was rewritten twice until it read as plain-language features / policies. Final form: 20 numbered bullets (M1–M20), each 1–2 sentences.
- **§4 Functional Flows** captured eight flows (sign-up, connect source, ingest transaction [central pipeline], cap CRUD, category override, threshold→push, monthly rollover, daily reconciliation) as numbered steps with inline `Fail →` branches.
- **§5 Implementation Notes** was first written long (11 sub-sections), then trimmed aggressively per user request — "no need to duplicate trivial standards, just main decisions" — down to 6 sub-sections covering only project-specific choices (tech-stack constraints, conventions, domain entities, audit/retention, security decisions, must-not-violate rules).
- **§6 Context** was reduced to a 1-minute scan: external services + fixtures (Beginning), 8 capability bullets + 5 artifact bullets (Ending).

### 4. Mid-stream scope tightenings

Several scope corrections cascaded through the spec:

- **Authentication added** (M1 email-OTP, M2 step-up token enforcement on sensitive endpoints).
- **Stale-source visibility dropped**, quiet hours dropped, user-facing notification inbox dropped (server-side `NotificationLog` retained).
- **Backend-only pivot**: mobile and ops-console UIs moved to out-of-scope. Cascaded across §1, §2 ("Platform" → "Surfaces"), §3 (M-bullets reframed as API behaviors), §4 (hybrid Client → Backend steps), and Implementation Notes.
- **Ops API itself moved to OOS** later; M20 was removed entirely and `OpsAccessLog` dropped from §5.

### 5. Low-level tasks (§7)

Approved a 21-task breakdown across 8 groups: Foundation (T01–T05), Domain model (T06–T09), Auth (T10–T11), Source management (T12–T13), Ingestion pipeline (T14–T16), APIs (T17–T18), Notifications (T19), Scheduled jobs (T20), Docs (T21).

Each task uses a compact format: *Serves* (M-id / F-id traceability) → *Prompt* (one-shot agent instruction) → file tree → `.env` and `Make` additions → *Acceptance* (verifiable criteria, including the SLO numbers that used to live in the trimmed §5).

Tasks were drafted in review-paced batches. One real design hole was caught and fixed during drafting: T06's `users.push_tokens_hashed` column was wrong — hashed tokens cannot be sent to. T19 adds a corrective migration introducing a `device_push_tokens` table with tokens encrypted via T03's KMS handle.

