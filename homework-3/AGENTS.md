# PennyBrake — Agent Guidelines

> These guidelines apply to any AI coding agent (Claude Code, Copilot, Cursor, etc.) working in this repository. They take precedence over any generic defaults the tool may have.

---

## 1. Project context

**PennyBrake** is a regulated FinTech backend service. It ingests bank transaction events, evaluates them against user-defined monthly spending caps, and dispatches push notifications at 50 / 80 / 100 % thresholds. It never authorises or blocks transactions, and never stores PANs, CVVs, or IBANs.

The spec lives in `specs/spending-caps-app.md` (structure, flows, implementation notes, must-not-violates). Read in case need additional details for request.

---

## 2. Tech stack

| Layer | Technology |
|-------|-----------|
| Language | Python 3.12 |
| Web framework | FastAPI 0.115+ |
| ORM / migrations | SQLAlchemy 2.0 (async) · Alembic 1.13+ |
| Validation | Pydantic 2.x + pydantic-settings |
| Database | PostgreSQL (ACID; managed in prod, Docker in dev) |
| Queue | Redis Streams in dev; interface-compatible with SQS / RabbitMQ in prod |
| Secret vault | KMS envelope encryption (LocalStack in dev) |
| Push | Firebase Cloud Messaging (FCM) |
| Structured logging | structlog 24+ |
| HTTP client | httpx 0.27+ |
| Testing | pytest 8+ |
| Lint / format | ruff 0.6+ |
| Type checking | mypy 1.11+ strict mode on `app/` |

Stick to this stack. Do not introduce a new dependency without flagging it explicitly and explaining what it simplifies/improves.

---

## 3. Domain rules (non-negotiable)

These rules come from the spec's must-not-violate list (§5.6). Every one of them is a hard boundary. If a task appears to require violating one, **stop and flag it** — it is a spec bug, not a reason to bend the rule.

1. **No PAN / CVV / IBAN storage or logging.** Drop forbidden fields at the adapter boundary; emit `DataQualityAudit`. If the log pipeline sees a 13–19 digit run, it masks it automatically (T05) — that masking is defence-in-depth, not a substitute for never receiving those fields in the first place.

2. **Money is always integer minor units.** Never use `float` or `decimal.Decimal` in the persistence path for amounts. Use `Decimal` only for in-memory FX arithmetic (banker's rounding), then convert to `int` before writing. A test must assert zero `float` usage in any module that touches amounts.

3. **`AuditEvent` and `PeriodSnapshot` are append-only.** The app DB role has `UPDATE` / `DELETE` revoked on those tables (T02's `revoke_mutations`). Never write application logic that tries to update or delete a row in them.

4. **Webhook body must not be logged before signature verification.** Verify the HMAC first; only then is it safe to log any part of the payload. Invalid-signature requests get a generic `401`; no payload body appears in any log line for those requests.

5. **Never access another user's data inside a user-context handler.** Enforce ownership at the data layer (query always includes `WHERE user_id = :current_user`), not only at the API layer.

6. **Push notification payloads must never include card numbers, full account numbers, or full merchant addresses.** The payload composer has its own redaction pass; a dedicated test (`test_payload_redaction.py`) must assert this for every template and fixture combination.

7. **Client-supplied timestamps are never authoritative.** Use `now()` from the server / DB for `created_at`, `posted_at`, and all period-boundary calculations.

8. **Notification dedup check must never be skipped.** Before dispatching any push, check `notification_logs` for `(user_id, cap_id, threshold_pct, period_snapshot_id) WHERE status='sent'`. The partial-unique index enforces it at the DB level, but the application-side check must also exist so failures surface before a DB round-trip.

9. **Webhook ack budget is 200 ms.** The endpoint enqueues and returns `200`. No DB calls, no adapter logic, no synchronous external calls on the hot path.

---

## 4. Code conventions

### IDs
- All entity IDs are ULIDs with a type prefix: `usr_`, `cap_`, `txn_`, `src_`, `evt_`, `ntf_`, etc.
- Generate ULIDs at the application layer; never let the DB generate a UUID and call it an ID.

### Money
- Column name suffix: `_minor` (e.g. `amount_minor`, `period_spend_minor`, `base_amount_minor`).
- Python type in business logic: `int`.
- DB column type: `BIGINT`.
- FX intermediate: `decimal.Decimal`; always `ROUND_HALF_EVEN` (banker's rounding).

### Time and periods
- All DB timestamps in UTC.
- Period boundaries computed from `user.timezone` (IANA string); use `zoneinfo` from stdlib.
- Never hard-code a UTC offset — always look up the IANA zone.

### Enums
- Define all domain enums in `app/domain/` (not inline in model files).
- DB enums are declared as `ENUM` types in Alembic migrations (not `VARCHAR` with a CHECK). This makes invalid values fail at the DB level, not only in Python.

### Error responses
- All error responses include `{ "code": "<machine_readable_slug>", "detail": "..." }`.
- Cross-tenant path lookups return `404`, never `403` — do not leak resource existence.
- Validation errors use `422` (FastAPI default via Pydantic).
- Optimistic-concurrency conflicts use `409` with `code: stale_version`.

### Logging
- Use structlog everywhere. Never use `print()` or the stdlib `logging` module directly.
- Bind `request_id` via middleware; it must appear on every log line inside a request.
- Do not log raw request bodies, query parameters, or response bodies at `INFO` level.
- Use `log.bind(...)` to add structured fields rather than embedding values in the message string.

### Testing
- Mirror the `app/` tree under `tests/`.
- Every module that contains business logic must have a corresponding unit-test file.
- Pure functions (the cap evaluator, the MCC categoriser, the payload composer) must be tested in complete isolation from I/O.
- Integration tests hit a real Postgres and real Redis (via `make up`); mock only the FCM HTTP endpoint and the email provider.
- End-to-end fixture tests use `make replay-fixture` to drive the full pipeline.

---

## 5. Security and compliance constraints

| Concern | Constraint |
|---------|-----------|
| Authentication | Every endpoint must declare `[SessionAuth]` or `[SessionAuth, StepupAuth]` in the OpenAPI security scheme. No unauthenticated endpoints exist except `/health`, `/version`, and `/webhooks/aggregator-events` (which uses HMAC, not user auth). |
| Step-up gating | `connect/start`, `connect/complete`, `GET /sources`, `export-data` require a valid step-up claim. Enforce via `require_stepup` dependency, not ad-hoc checks. |
| Secret storage | Aggregator source tokens live only in the KMS vault. The DB stores the `EncryptedHandle` JSONB. Never pass a plaintext token to a function that touches the DB. |
| Webhook signing | HMAC-SHA256 with a per-adapter secret. Support two concurrent secrets per adapter for zero-downtime rotation. Verify before any other action. |
| Audit | Every cap mutation, source connect/disconnect, category override, and all system actions (rollover, reconciliation) must write an `AuditEvent`. Missing an audit write is a bug, not a minor omission. |
| Retention | Audit events: 7 years. Transactions / evaluations: 24 months then archived. Notification log: 12 months. Do not write code that deletes records outside these windows. |
| PII | `users.email` is the only PII field in PennyBrake's own schema. Apply `citext` for case-insensitive lookups. On user deletion, scrub PII fields after the 30-day grace period — do not drop audit rows. |

---

## 6. Edge-case handling expectations

When working on any task, the agent must consider and explicitly handle:

- **Duplicate events**: idempotency gate on `(adapter_id, external_event_id)` before any insert. Drop silently; increment a metric.
- **Out-of-order events**: pending then posted is the normal path. Posted then pending (smaller `update_seq`) must leave the posted state intact — never downgrade status.
- **Concurrent cap edits**: optimistic concurrency via `caps.version`; return `409` on conflict.
- **Late-arriving transactions**: if `posted_at` falls inside a closed period, write an `Adjustment` row linked to the snapshot; do **not** mutate the snapshot or retrigger alerts.
- **Re-arm logic**: when refund or recategorisation drops spend below a previously-crossed threshold, re-arm the threshold flag. The evaluator's pure function is the single source of truth for this logic.
- **No active device tokens**: write `notification_logs` row with `status='no_target'`; ack the queue message; do not raise an error.
- **FCM provider failures**: exponential backoff, up to 6 attempts; record each attempt in `notification_logs`; mark `failed` after the last.
- **Invalid webhook signatures**: reject immediately with `401`; do not log the body; increment a security metric.
- **Forbidden fields from aggregator**: drop at adapter boundary; emit `DataQualityAudit`; continue processing — do not fail the entire event.

---

## 7. Verification expectations

- Before marking any task complete, confirm all **acceptance criteria** in that task's section of `specs/spending-caps-tasks.md` are met.
- Performance benchmarks in acceptance criteria are real requirements, not suggestions. Run them and report the result.
- For tasks that touch `AuditEvent` or `PeriodSnapshot`, verify append-only enforcement by executing a test that attempts an `UPDATE` as the app DB role and asserts it fails.
- After any change to the push payload composer, re-run `test_payload_redaction.py` against all fixture + template combinations.

