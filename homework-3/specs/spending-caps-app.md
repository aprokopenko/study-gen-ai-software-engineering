# PennyBrake — Spending-Caps Application: Specification

> Where this document and the Low-Level Tasks disagree, the Mid-Level Objectives take precedence — the tasks are an implementation slicing, not a redefinition of intent.

---

## 1. High-Level Objective

**What we are building.** PennyBrake is a **backend service** powering a personal spending-caps experience: it lets a user set **monthly spending caps** (overall or per category), aggregates their transactions from connected bank accounts and cards through a stable adapter contract, evaluates each transaction against those caps in near-real-time, and dispatches a **push notification** (via FCM) when their spending crosses **50%, 80%, or 100%** of any cap. The iOS / Android client and the ops-console web UI that consume this backend are **out of scope** for this specification.

**Scope boundary.** PennyBrake **observes and informs**; it never authorizes or blocks transactions, never stores PAN / CVV / IBAN, and never holds source-institution credentials — those responsibilities live with a regulated aggregator partner (e.g. Plaid, TrueLayer). v1 ships with a single **mock** aggregator adapter so the backend is demonstrable end-to-end without a vendor contract.

---

## 2. Scope & Stakeholders

### 2.1 Surfaces

This spec covers the **backend service**. The iOS / Android client is out of scope. An internal Ops API + console is planned for after v1 but **out of scope here** — no ops endpoints are built in v1; ops needs are met manually via DB access until then.

- **Public mobile-facing API** — consumed by an iOS / Android client (client OOS).
- **Webhook ingestion endpoint** — receives signed aggregator events.
- **Push dispatch** — backend dispatches notifications to **Firebase Cloud Messaging** (FCM for Android, FCM-as-APNs-relay for iOS). v1 has **no email channel**.

### 2.2 Actors

| Actor | Role | Authority |
|---|---|---|
| **End-user** (cap owner) | Defines and manages caps, receives alerts, overrides individual transaction categories, manages connected sources. | Full CRUD on **own** data only; no access to any other user's data. |
| **Ops / compliance analyst** | Internal stakeholder. Incident triage, support, abuse investigation. | **No backend surface in v1**; planned post-v1 as a read-only API with logged justification. All audit events they will need are still produced. |
| **Aggregator partner** | External system supplying transaction events. Mocked in v1; pluggable via Adapter contract. | Pushes signed webhook events to the single ingestion endpoint. |
| **Push carrier** | External push provider (FCM). | Delivers outbound push notifications. |
| **System actor** | Background jobs: rollover, reconciliation, source-health probe. | Tagged distinctly in audit events; cannot impersonate human actors. |

---

## 3. Mid-Level Objectives

Each bullet is one observable feature or policy the v1 system must deliver, framed for product / BA / stakeholder review. Tasks in §7 reference these by ID. Concrete numbers, schemas, and mechanics live in §5 (Implementation Notes).

- **M1 — Email sign-up and sign-in via one-time code.** The backend exposes an email-OTP API: clients submit an email and receive a 6-digit one-time code by email, then exchange the code for a session token. v1 uses no passwords and no third-party identity providers. If a user loses access to their email or device, recovery is handled manually by the support team out-of-band.
- **M2 — Step-up token enforcement on sensitive endpoints.** The backend requires a valid short-lived (5-minute) step-up token claim on sensitive endpoints: connect a source, list connected sources, export data. Step-up tokens are issued via a device-bound challenge flow; the mobile-side biometric prompt that gates the device key is **out of scope** for this spec.
- **M3 — Connect a source.** The backend exposes an API for connecting bank accounts and cards through a regulated aggregator partner (e.g. Plaid, TrueLayer); the flow delegates login and credential handling to that partner. PennyBrake never sees the user's banking password.
- **M4 — Multi-vendor by design.** The system supports multiple aggregator vendors over time through one stable internal contract; v1 ships with a single **mock** aggregator so the product is fully demonstrable without a real vendor agreement.
- **M5 — Manage connected sources.** The backend exposes APIs to list connected sources and disconnect any of them at any time. After disconnection no new transactions arrive from that source, but the user's historical data and caps remain intact.
- **M6 — One faithful transaction history.** Every real-world transaction the user makes is captured exactly once. Pending transactions that later post — and refunds that reverse them — are reconciled into a single consistent history, with no duplicate or ghost records.
- **M7 — Foreign-currency transactions.** Transactions made in a currency other than the user's base currency are converted to the base currency on arrival. The original currency, original amount, and conversion rate are kept on the record so the user (and ops) can always trace where a converted amount came from.
- **M8 — Sensitive data never enters PennyBrake.** Card numbers, security codes, and full bank account / IBAN numbers are never stored or logged by PennyBrake. If a connected source ever sends such fields, they are dropped at the boundary and an internal data-quality alert is raised.
- **M9 — Near-real-time alerts.** From the moment a transaction is recorded by the aggregator, the user receives any cap-threshold alert in under a minute under normal load (target: within 30 seconds for 95% of cases).
- **M10 — Monthly caps, with audit.** Users can create, edit, and deactivate **monthly** spending caps. Each cap is either an **overall monthly limit** or **scoped to one category**. Every change is recorded in a tamper-evident audit log (who, when, before / after) that is **append-only** — no one inside the company can edit or delete entries after the fact.
- **M11 — Curated, explainable categorisation.** Every transaction is automatically sorted into one of a curated list of categories: Groceries, Dining, Transport, Shopping, Entertainment, Utilities, Health, Travel, Fees, Income, Other. Income (e.g. salary, deposits) is excluded from spending caps. The categorisation ruleset is versioned, so historical decisions can always be reproduced and explained.
- **M12 — Override a transaction's category.** The backend exposes an API to change the category of a single transaction. The override triggers re-evaluation of any affected caps in the transaction's period, and any newly triggered alert dispatches immediately.
- **M13 — Three fixed thresholds.** Each cap has three notification thresholds: **50% / 80% / 100%** of the limit. v1 uses these fixed values and does not let users move them.
- **M14 — One alert per threshold per month, with re-arm. No in-app notification history.** Each threshold triggers an alert **at most once per cap per month**, even if the underlying event is replayed. If a refund or recategorisation drops spend back below a threshold, that threshold is **re-armed** and can trigger again on a later crossing. v1 does **not** expose a notification history inside the app — past alerts live only on the user's device in their OS notification centre.
- **M15 — Traceable alerts.** Every alert can be traced back to the exact transaction that caused it, so support can answer "why did I get this notification?" without guesswork.
- **M16 — Push dispatch.** The backend dispatches cap alerts as **push notifications** via FCM. v1 has no quiet hours and no email or SMS channel — the working assumption is that users spend awake and a single channel keeps the alert UX simple.
- **M17 — Privacy-safe alert content.** Alert bodies never include the user's card number, full bank account number, or full merchant address. Alerts contain only the cap name, threshold reached, period-to-date amount, and a deeplink into the app.
- **M18 — Immutable monthly history.** On the first of each month (in the user's local timezone), each cap's prior-month total is **frozen** into history and a fresh month begins. Late-arriving transactions for a closed month are recorded as adjustments and shown in history, but do not retro-trigger alerts.
- **M19 — Daily integrity check.** A daily reconciliation job verifies that each closed month's total matches the sum of its transactions plus adjustments. Any mismatch is treated as an incident and raised to the ops team.

---

## 4. Functional Flows

Each flow describes one observable behavior of the system as a numbered sequence of steps. Failure branches appear inline as **Fail →** notes under the relevant step. References like *(M8)* point to the Mid-Level Objective the step upholds.

---

### F1 — Sign-up and sign-in (email one-time code)

*Used by both new and returning users; the path forks on step 4 depending on whether the email is already known.* **(M1, M2)**

1. Client submits the user's email to the sign-in API.
2. Backend sends a 6-digit one-time code to that email. Code-send is rate-limited per email per hour and per IP per hour.
   - **Fail →** email provider error: client receives a generic "we'll resend shortly" response; ops alerted via the notification log.
   - **Fail →** rate limit exceeded: HTTP 429; no code is sent.
3. Client submits the verification code within the 10-minute window.
   - **Fail →** wrong code: up to 5 attempts allowed; after the 5th the email is locked for 15 minutes.
   - **Fail →** expired code: client is told to request a new one (back to step 2).
4. Backend verifies the code and branches:
   - **New email** → backend creates a fresh user account and returns an enrolment status; the client then completes its device-bound challenge (mobile biometric OOS) and exchanges device attestation for a session.
   - **Known email** → backend loads the user account; the client completes its device-bound challenge and obtains a session token.
5. Backend issues a session token bound to the device via a `device.id` claim.

---

### F2 — Connect a source

*Client adds a bank account or card; backend never sees the user's banking credentials.* **(M3, M2)**

1. Client calls the connect-source initiation endpoint. Backend requires a valid step-up token claim on the request (M2).
   - **Fail →** missing or expired step-up token: HTTP 403; client obtains a fresh step-up token (device challenge flow, OOS) and retries.
2. Client opens the aggregator's hosted authentication flow in a secure web view.
3. User authenticates with their bank inside the aggregator's flow.
   - **Fail →** aggregator returns `auth_failed` or `user_cancelled`: no source is created.
4. Aggregator returns an opaque source token to the client.
5. Client posts the token + aggregator id to the connect-source completion endpoint.
6. Backend validates the token shape, vaults it in the secret store, creates the source record in the "connecting" state, and writes an audit event for the connect action **(M10)**.
   - **Fail →** the same external account is already connected by this user: HTTP 409.
7. Source transitions to "active" once the aggregator delivers its first event into the ingestion pipeline (F3).

---

### F3 — Ingest a transaction event

*The central pipeline. Every transaction flows through this path; every cap evaluation and every push notification descends from here.* **(M4, M5, M6, M7, M8, M9)**

1. Aggregator posts a signed webhook payload to the single ingestion endpoint.
2. Endpoint verifies the payload signature against the aggregator's public key.
   - **Fail →** invalid signature: reject with HTTP 401; do **not** log the payload body; increment a security metric.
3. Endpoint acknowledges the webhook within the ack budget (under 200 ms) and enqueues the payload for async processing.
4. A worker dequeues the payload, identifies the source from the signed header, and routes it to that source's internal Adapter.
5. The Adapter transforms the vendor payload into a normalised transaction event, dropping any forbidden fields at the boundary (card number, security code, full IBAN) **(M8)**.
   - **Fail →** forbidden field detected: drop the field, emit a data-quality audit event, continue processing.
6. The system checks the event's idempotency key against the existing transactions store **(M6)**.
   - **Fail →** duplicate event: drop with a metric increment; flow ends.
7. The system classifies the event:
   - New transaction → insert a new transaction record.
   - Pending-to-posted update of an existing transaction → merge into the existing record (one record per real-world purchase).
   - Refund / reversal → insert as a new transaction record linked to the original.
8. For foreign-currency transactions, the FX rate carried on the event is applied; original currency, original amount, FX rate, and base amount are all persisted on the record **(M7)**.
9. The system categorises the transaction by its merchant category, recording the ruleset version used **(M11)**.
10. The system recomputes period-to-date totals for every cap the transaction touches.
11. For each newly crossed threshold (50% / 80% / 100%), a cap-evaluation record is written **(M13, M14, M15)**.
12. For each new cap-evaluation, the system hands off to the notification dispatch flow (F6).

---

### F4 — Create, edit, or deactivate a cap

*Routine cap management. Audited but not step-up-gated.* **(M10)**

1. Client submits a cap create / edit / deactivate request to the cap API: type (overall or category), category (if applicable), amount, period (monthly).
2. Backend validates the input: amount is positive, category is from the curated list, no duplicate cap exists for the same scope.
   - **Fail →** validation error: return a structured error; no state change.
3. Backend writes the cap (or updates an existing one, incrementing its version), and writes a before/after audit event tagged with the user as actor **(M10)**.
   - **Fail →** version conflict (two clients editing the same cap): second write returns HTTP 409; client refreshes and retries.
4. If this is an **edit** and the new amount is below current period spend, the backend immediately runs a cap evaluation with cause `cap_reduced` and hands off to F6.
5. If this is a **deactivation**, no further evaluations occur for that cap; the in-progress period closes immediately with its current total as the historical snapshot **(M18)**.
6. Backend returns the updated cap to the client.

---

### F5 — Override a transaction category

*User changes the category of one transaction; affected caps recompute and may fire new alerts.* **(M12)**

1. Client submits a category override request for a transaction (new category from the curated list).
2. Backend writes the override against the transaction and emits an audit event.
3. Backend identifies all caps in the transaction's period that touch the old or new category.
4. Backend re-evaluates each affected cap:
   - If spend dropped below a previously-crossed threshold → threshold is **re-armed** for that cap and period **(M14)**.
   - If spend crossed a new threshold → a cap-evaluation is written and the flow hands off to F6.
5. Backend returns the updated transaction; cap-status read endpoints reflect the new state.

---

### F6 — Threshold crossing → push delivery

*Every push notification PennyBrake sends originates here. Triggered by F3, F4, F5, or F7.* **(M9, M14, M15, M16, M17)**

1. A cap-evaluation record is committed (the trigger).
2. Notification dispatcher checks the dedup key `(user, cap, threshold, period)` against the notification log **(M14)**.
   - **Fail →** key already marked as sent: no-op; flow ends.
3. Dispatcher composes the push payload: cap name, threshold reached, period-to-date amount, deeplink. Payload **never** includes card number, full account number, or full merchant address **(M17)**.
4. Dispatcher pushes to FCM with the user's active device tokens.
   - **Fail →** FCM provider 5xx: retry with exponential backoff for up to 1 hour; then mark the attempt failed.
   - **Fail →** device token rejected (token invalid / app uninstalled): remove the token from the user's active set; do not retry on it.
   - **Fail →** user has no active device tokens: skip the push, mark the attempt `no_target`, flow ends without notifying.
5. Each attempt — success or failure — is recorded in the notification log with status, attempt count, failure reason, and timestamps. v1 does **not** show this log inside the app **(M14, M16)**.

---

### F7 — Monthly rollover

*Closes one month's snapshot for each cap and opens the next month, in the user's local timezone.* **(M18)**

1. A scheduled job triggers at the top of every UTC hour.
2. The job queries users whose local time has just crossed into the 1st of the month.
3. For each such user, for each active cap:
   - Snapshot the closing period's total into an immutable historical record.
   - Open a fresh current-period record anchored to local-midnight.
   - Write a system-actor audit event for the rollover **(M10)**.
4. If the user has 0 active caps, no work is done for them.
5. Late-arriving transactions whose `posted_at` falls inside a now-closed period (arriving via F3 after rollover) are recorded as adjustment records linked to the closed period; they **do not** mutate the snapshot and **do not** retro-trigger alerts **(M14, M18)**.

---

### F8 — Daily reconciliation

*Verifies that each closed-period snapshot still equals the sum of its underlying transactions plus adjustments.* **(M19)**

1. A scheduled job runs once per day at a fixed regional time.
2. For every period closed in the prior 24 hours, the job computes:
   - `expected = Σ in-scope transactions + Σ adjustments`
   - `actual = stored period_spend snapshot`
3. **Match** → write a `reconciliation_ok` audit event; period stays in `verified` state.
4. **Mismatch** → write a `reconciliation_incident` audit event, raise an ops alert, mark the period as `under_investigation` **(M19)**.
5. Incident follow-up (review, adjustment correction, escalation) is handled out-of-band; v1 has no ops API / console surface for this. The `reconciliation_incident` audit event is the artifact ops works from.

---


# 5. Implementation Notes

## 5.1 Tech stack constraints

- ACID-capable relational DB for transactional state.
- Durable at-least-once queue for ingestion + notification dispatch.
- Managed KMS / secret vault with envelope encryption for source tokens.
- Firebase Cloud Messaging for push dispatch (FCM for Android, FCM-as-APNs-relay for iOS).
- An internal Ops API + console is planned for after v1; no ops endpoints in v1.

## 5.2 Conventions

- **Money** stored as integer minor units. No floats anywhere in the money path.
- **IDs** are ULIDs with type prefixes (`usr_`, `cap_`, `txn_`, `src_`, …).
- **Period boundaries** anchor to local 00:00 on the 1st of the month in `user.timezone` (IANA).

## 5.3 Domain entities

System-of-record contracts; full field shapes are sketched in §7 tasks.

- **User** — identity (email, timezone, base currency), active push-token list (hashed), status.
- **Source** — one external-account connection; holds only a KMS handle to the aggregator token, never the token itself.
- **Transaction** — one record per real-world purchase; pending and posted events merge into the same record. Carries original-currency and FX fields.
- **Cap, CurrentPeriod, PeriodSnapshot, Adjustment** — cap definition + running period total; `PeriodSnapshot` is immutable; `Adjustment` covers late-arriving transactions in closed periods.
- **CategoryOverride, CapEvaluation, NotificationLog** — user-set categories, threshold-crossing records, push attempts (authoritative source for dedup).
- **AuditEvent** — append-only at the DB-role level; covers every cap mutation, source connect/disconnect, override, and system action.

## 5.4 Audit, retention, deletion

- `AuditEvent`, `PeriodSnapshot`: append-only — application DB user has no `UPDATE` / `DELETE` privilege on those tables.
- **Retention**: audit **7 years**; transactions & cap evaluations **24 months** then archived; notification log **12 months**.
- **User deletion**: 30-day soft-delete grace; afterwards PII is scrubbed but audit records retained for the 7-year window.

## 5.5 Security decisions (non-standard parts)

- **Source tokens** live only in the secret vault; the app DB holds an opaque KMS handle.
- **Email OTP**: 6 digits, 10-minute TTL, max 5 attempts; rate limit **5 codes/hour per email**, **20/hour per IP**; **15-minute lockout** after 5 failed entries.
- **Sessions**: 1-hour server-side; refresh token (7 days) bound to a `device.id` claim from a device-bound challenge flow (mobile-side biometric OOS).
- **Step-up token**: short-lived (5 min, no persistent flag); required by the backend on connect-source, list-sources, and export-data endpoints. Issuance flow lives between client and backend; this spec covers the backend's verification and enforcement only.
- **Webhook signing**: HMAC per aggregator, 90-day rotation; signature verified **before** any payload logging or persistence.
- **Log masking**: log pipeline masks any 13–19-digit sequence by default as defence in depth against an adapter regression.

## 5.6 Must-not-violate rules

Any task that appears to require violating one of these is a spec bug — flag it instead of implementing.

1. **Never** persist or log PAN, CVV, full IBAN, or full account number. Drop at the boundary and emit `DataQualityAudit`.
2. **Never** use floating point for money. Integer minor units only.
3. **Never** mutate or delete `AuditEvent` or `PeriodSnapshot` after writing.
4. **Never** log a webhook payload body before signature verification has succeeded.
5. **Never** access another user's data inside a user-context handler. Enforce at the data layer, not just the API layer.
6. **Never** include sensitive content in a push notification body.
7. **Never** trust client-supplied time for authoritative timestamps.
8. **Never** skip the notification dedup check.
9. **Never** add a synchronous external network call to the webhook ack hot path; ack first, process async.

---

# 6. Context (Spending-Caps App — Backend)

## 6.1 Beginning context

**External services (assumed provisioned out-of-band)**

- Managed KMS / secret vault, with a master key created for envelope encryption.
- Firebase project with FCM enabled for the app's planned iOS and Android bundle IDs.
- Managed relational DB instance (empty schema), and a managed durable queue.

**Fixtures & tooling**

- `fixtures/mock_aggregator/` — hand-authored JSON event fixtures covering: posting, pending → posted, refund, foreign currency, unmapped MCC, and forbidden-field detection.
- Empty `db/migrations/` directory.
- A `make replay-fixture <name>` target stub that posts a fixture payload to the local webhook endpoint.

## 6.2 Ending context — working backend

**Capabilities**

- Replay a fixture → transaction persisted, categorised, FX-converted; cap evaluated; push dispatched to FCM.
- Email-OTP sign-up / sign-in with session + step-up tokens.
- Source APIs: connect (mock), list, disconnect.
- Cap APIs: create / edit / deactivate monthly caps; audited.
- Transactions APIs: list and read; per-transaction category override.
- Threshold alerts at 50 / 80 / 100 — once per period, re-arm on retreat.
- Monthly rollover (hourly cron) and daily reconciliation (scheduled).

**Artifacts**

- DB schema migrated; append-only role on audit / snapshot tables.
- `mcc_map_v1` versioned code artifact.
- Test suite (unit, integration, fixture-driven E2E).
- API docs (OpenAPI).
- Install / dev / on-call docs.

---

# 7. Low-Level Tasks

See tasks breakdown in [spending-caps-tasks.md](spending-caps-tasks.md)
