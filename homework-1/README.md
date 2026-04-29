# 🏦 Homework 1: Banking Transactions API

> **Student Name**: Oleksandr Prokopenko  
> **Date Submitted**: 28.04.2026  
> **AI Tools Used**: Kiro CLI, Kiro CLI (JetBrains plugin), Claude Code CLI, Claude Code (VS Code plugin)

---

## 📋 Project Overview

A REST API for bank account transactions built with **PHP 8.5** and **Slim 4**, using **Medoo** over **SQLite** for persistence and **PHP-DI** for dependency injection. The app runs in Docker (Nginx + PHP-FPM) on port `3000`.

**Tech stack:**
- Runtime: PHP 8.5 (FPM) + Nginx via Docker
- Framework: Slim 4 (PSR-7)
- Database: SQLite via Medoo 2.x
- DI Container: PHP-DI 7

---

## 🔌 API Endpoints

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/` | Health check |
| `POST` | `/transactions` | Create a transaction |
| `GET` | `/transactions` | List / filter transactions |
| `GET` | `/transactions/{id}` | Get a single transaction |
| `GET` | `/accounts/{accountId}/balance` | Get balances per currency |
| `GET` | `/accounts/{accountId}/summary` | Get deposit/withdrawal summary |

### Transaction fields

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `type` | string | ✅ | `deposit`, `withdrawal`, `transfer` |
| `amount` | number | ✅ | Positive, max 2 decimal places |
| `currency` | string | ✅ | ISO 4217 code (e.g. `USD`, `EUR`) |
| `fromAccount` | string | transfers/withdrawals | Format: `ACC-XXXXX` |
| `toAccount` | string | deposits/transfers | Format: `ACC-XXXXX` |
| `status` | string | ❌ | Defaults to `completed` |

### Filter parameters for `GET /transactions`

| Param | Example |
|-------|---------|
| `accountId` | `?accountId=ACC-00001` |
| `type` | `?type=deposit` |
| `from` | `?from=2025-01-01` |
| `to` | `?to=2025-12-31` |

> 📸 Endpoint demo screenshots (PHPUnit results, real request examples) are available in the [`demo/`](demo/) folder.

---

## 🏗️ Solution Approach

The project follows a straightforward layered structure:

```
Controller → Repository → Medoo (SQLite)
```

- **Controllers** handle HTTP request/response and delegate to the repository.
- **TransactionValidator** enforces business rules: positive amounts, ISO 4217 currencies, account ID format (`ACC-XXXXX`), and required fields per transaction type.
- **TransactionRepository** wraps all DB queries. Balance and summary calculations are done in PHP by iterating completed transactions for an account, keeping the DB layer simple.
- **PHP-DI** wires everything together via autowiring — no manual binding needed.
- The SQLite database file lives in `/var/www/data/` (Docker volume), initialised by `bin/setup.php`.

---

## 🧪 Testing

PHPUnit tests cover all endpoints and validation rules. Tests use an in-memory SQLite database (separate from the app DB) and a shared `AppTestCase` base class with helpers for seeding data and asserting responses.

Run tests:
```bash
make phpunit
```

Test coverage includes: creating transactions, validation errors, listing and filtering, balance calculation across currencies, transfer direction handling, and ignoring non-completed transactions.

A ready-made [`http-client/api.http`](http-client/api.http) file is also included for manual endpoint testing. It works with JetBrains HTTP Client, VS Code [REST Client](https://marketplace.visualstudio.com/items?itemName=humao.rest-client) extension, and [httpYac](https://httpyac.github.io/).

---

## 🤖 AI Tools Used

Four AI tools were used across different phases of development. Interaction screenshots are in [`docs/screenshots/`](docs/screenshots/), and detailed session-by-session reports are in [`docs/`](docs/).

### Tools

| Tool | Used for |
|------|----------|
| **Kiro CLI** | Planning (Tasks 2–4), implementation (project setup, Tasks 2–4) |
| **Kiro CLI (JetBrains plugin)** | Implementation (Tasks 2–4), tech stack discussion |
| **Claude Code CLI** | Planning (Task 1, using Ultraplan), implementation (Task 1) |
| **Claude Code (VS Code plugin)** | Tech stack discussion, early exploration |

### Workflow

**Project setup** — Used Claude Code (VS Code plugin) and Kiro (JetBrains plugin) to discuss which technologies would work well together (Slim vs Laravel, Medoo vs Eloquent, etc.). Settled on the lightweight Slim + Medoo + PHP-DI stack. Kiro CLI then scaffolded the full Docker + Nginx + PHP-FPM project.

**Task 1 (Core API)** — Planned with Claude Code CLI using the Ultraplan feature (initially tried the web UI, but moved back to the terminal due to issues with Ultraplan in the browser). The plan was thorough — it also caught a few bugs in the initial scaffold code. Implementation was executed by Claude Code CLI following the plan. Only minor manual adjustments were needed; the generated code was solid.

**Tasks 2–4 (Validation, Filtering, Summary)** — Planned with Kiro CLI, implemented with Kiro in JetBrains. Both planning and implementation required only minor corrections. The agents handled multi-file changes well — touching validators, controllers, tests, and routes in a single pass.

### Observations

- **Planning:** Claude Opus 4.7 in planning mode produced slightly more detailed and structured plans than Kiro Opus 4.6. Both were usable without major rework.
- **Implementation:** Both tools performed well on scoped tasks.
- **Biggest friction:** Most intervention was in Docker/Nginx configuration, which AI didn't finetune at the first run.

---

<div align="center">

*This project was completed as part of the AI-Assisted Development course.*

</div>
