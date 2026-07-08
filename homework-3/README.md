# Homework 3 — Specification-Driven Design

**Student**: Alex Prokopenko  
**Date**: May 12, 2026

---

## Task summary

This submission is a specification package for **PennyBrake** — a backend service that lets users connect bank accounts and cards, define monthly spending caps (overall or per category), and receive push notifications at 50 / 80 / 100 % of each cap as transactions arrive in near-real time. No code is included; the deliverable is the specification itself.

| File | Purpose |
|------|---------|
| [`specs/spending-caps-app.md`](specs/spending-caps-app.md) | Full layered specification: high-level objective → stakeholders → mid-level objectives (M1–M19) → functional flows (F1–F8) → implementation notes → beginning/ending context |
| [`specs/spending-caps-tasks.md`](specs/spending-caps-tasks.md) | 21 implementable tasks (T01–T21) with acceptance criteria, file trees, env-var additions, and Make targets |
| [`AGENTS.md`](AGENTS.md) | AI agent guidelines: domain rules, tech stack, code conventions, security constraints, edge-case and verification expectations |
| [`.claude/CLAUDE.md`](.claude/CLAUDE.md) | Claude Code standing orders: hard limits, naming conventions, patterns, testing defaults |

---

## Rationale

### Why this structure?

The spec follows a deliberate top-down layering:

**High-level objective → Mid-level objectives → Functional flows → Implementation notes → Context → Tasks**

Mid-level objectives (M1–M19) are written in stakeholder-readable language — what changes in the world, not how the system achieves it. Numbers, schemas, and mechanics are pushed to Implementation Notes and per-task acceptance criteria. This separation means a BA or compliance analyst can review most of the spec without reading about database indexes, while an engineer implementing tasks has all the specifics they need without re-reading business intent.

Functional flows (§4) sit between objectives and implementation. They capture the observable sequence of steps and failure branches without committing to internal architecture — usable as both a design walkthrough artefact and a scaffold for per-task acceptance criteria.

The 21 tasks were sized to fit one pull request each, ordered by dependency, and end with criteria that are checkable by a human or usable directly as an AI agent prompt. Performance benchmarks appear inside acceptance criteria rather than in a separate non-functional section — this avoids the common failure mode where SLOs are documented but never tied to anything verifiable.

### Why these performance targets?

| Target | Value | Reasoning |
|--------|-------|----------|
| Alert latency p95 | 30 s end-to-end | FinTech UX expectation; Plaid / Stripe webhooks arrive in single-digit seconds, leaving budget for queue + evaluation + FCM. Labelled **assumed target**. |
| Webhook ack | < 200 ms | Aggregator partners retry on timeout; 200 ms hard budget avoids duplicate delivery and decouples ack from cap evaluation latency. |
| Cap evaluator (pure fn) | 1000 calls < 100 ms | Runs per cap per transaction at high velocity; must be allocation-light. Benchmark embedded in T16 acceptance criteria. |
| Monthly rollover | 10 k users × 5 caps < 30 min | Batched hourly; finishing in < 30 min leaves headroom before the next run. |
| Category override → cap reflects new spend | < 1 s p95 | User-initiated and synchronous from the user's perspective; covers recategorisation evaluation but not async push delivery. |

### Why this verification depth?

Every mid-level objective is traceable to at least one task, and every task has acceptance criteria that directly check it. Append-only enforcement (M10) is verified by attempting an `UPDATE` as the app DB role in a test — a documentation-only claim would be insufficient. Notification dedup (M14) is enforced at both the application layer and the DB layer (partial-unique index) and tested by replaying the same evaluation message twice and asserting no additional FCM calls.

---

## Industry best practices

The following practices are embedded in the spec with traceable references to the files and sections where they appear.

| # | Practice | Where in the spec |
|---|----------|------------------|
| 1 | **Regulated-boundary data minimisation** — forbidden fields (PAN, CVV, IBAN) are dropped at the adapter boundary and never stored or logged, eliminating PCI DSS scope for those fields entirely. | `specs/spending-caps-app.md` §1, §3 M8, §5.6 rule 1 |
| 2 | **Append-only audit log with DB-role enforcement** — `UPDATE` / `DELETE` revoked at the role level on `audit_events` and `period_snapshots`; 7-year retention aligns with PSD2 / MiFID II. | `specs/spending-caps-app.md` §3 M10, §5.4, §5.6 rule 3; `specs/spending-caps-tasks.md` T02, T08, T09 |
| 3 | **Envelope encryption for third-party credentials** — aggregator tokens never enter the app DB; the DB holds an opaque `EncryptedHandle`; key rotation is supported without re-encrypting existing handles. | `specs/spending-caps-app.md` §5.5; `specs/spending-caps-tasks.md` T03 |
| 4 | **Idempotency as a first-class primitive** — ingestion gated on `(adapter_id, external_event_id)`; notification dispatch gated on `(user, cap, threshold, period) WHERE status='sent'`; both enforced at the DB level with unique indexes, not only in application logic. | `specs/spending-caps-app.md` §3 M6, §4 F3 step 6, §5.6 rule 8; `specs/spending-caps-tasks.md` T07, T15 |
| 5 | **Webhook signature verify-before-log** — invalid-signature requests receive a generic `401` with no payload body in any log line; a timestamp replay window (90 s) prevents replayed valid requests. | `specs/spending-caps-app.md` §4 F3 step 2, §5.6 rule 4; `specs/spending-caps-tasks.md` T14 |
| 6 | **Step-up authentication for sensitive operations** — connecting a source or listing sources requires a short-lived (5 min), device-bound step-up token separate from the session token, limiting the blast radius of a compromised session. | `specs/spending-caps-app.md` §3 M2, §5.5; `specs/spending-caps-tasks.md` T11, T13 |
| 7 | **Structured logging with automatic PAN masking** — the log pipeline masks any 13–19-digit run as defence-in-depth against adapter regressions; the spec is explicit this does not replace boundary-level dropping. | `specs/spending-caps-app.md` §5.5; `specs/spending-caps-tasks.md` T05 |
| 8 | **Timezone-aware period boundaries** — monthly periods anchored to the user's IANA timezone, not UTC; rollover runs hourly and processes only users crossing local midnight on the 1st, avoiding a thundering-herd at UTC midnight. | `specs/spending-caps-app.md` §5.2, §4 F7; `specs/spending-caps-tasks.md` T20 |
| 9 | **Privacy-safe push payload content** — notifications contain only cap name, threshold %, period-to-date amount, and a deeplink; a dedicated redaction test asserts this across all templates and fixture combinations. | `specs/spending-caps-app.md` §3 M17, §4 F6 step 3, §5.6 rule 6; `specs/spending-caps-tasks.md` T19 |
| 10 | **Daily reconciliation as an integrity primitive** — closed-period snapshots verified daily against the sum of their transactions plus adjustments; any mismatch raises an ops incident recorded in the audit log. | `specs/spending-caps-app.md` §3 M19, §4 F8; `specs/spending-caps-tasks.md` T20 |
