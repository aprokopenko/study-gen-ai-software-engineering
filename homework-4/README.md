# User Registration Micro-Service

> **Student Name**: Oleksandr Prokopenko
> **Date Submitted**: 25.05.2026
> **AI Tools Used**: Claude Code (VS Code plugin/CLI), Kiro IDE

---

A minimal REST API micro-service for user registration, intentionally seeded with bugs and a security vulnerability for pipeline/agent exercises.

**Stack:** Node.js 22 + Express + Jest + supertest, containerized with Docker.

---

## Overview

| Endpoint | Method | Auth | Description |
|---|---|---|---|
| `/api/register` | POST | — | Create a new user |
| `/api/login` | POST | — | Log in, receive a token |
| `/api/profile` | GET | Bearer token | Get current user profile |

### Seeded defects

| # | Location | Description |
|---|---|---|
| 001 | `src/routes/auth.js` | Duplicate email registration allowed (no uniqueness check) |
| 002 | `src/routes/auth.js` | Login always fails — typo `user.pasword` instead of `user.password` |
| 003 | `src/store/users.js`, `src/middleware/auth.js` | Plaintext password storage + forgeable `base64(email)` auth token |

Full details in `context/bugs/XXX/bug-context.md`.

---

## Setup

**Prerequisites:** Docker and Docker Compose.  No local Node.js needed.

```bash
# 1. Clone / enter the project directory
cd homework-4

# 2. Build the Docker image
make build

# 3. Start the service (runs on port 3000)
make up

# 4. Stop the service
make down
```

### All Makefile targets

| Target | Description |
|---|---|
| `make build` | Build the Docker image |
| `make up` | Start the service in the background |
| `make down` | Stop and remove containers |
| `make test` | Run the Jest test suite inside Docker |
| `make lint` | Run ESLint on `src/` |
| `make shell` | Open a shell inside the container |
| `make logs` | Tail container logs |
| `make restart` | `down` then `up` |
| `make install` | Run `npm install` inside the container |

---

## Bug-Fix Pipeline

The project includes a 4-agent pipeline that automatically researches, fixes, security-reviews, and writes tests for every seeded bug.

### Agents

| Agent | Model | Role |
|---|---|---|
| **Codebase Researcher** *(inline)* | opus | Reads `bug-context.md`, traces the defect in `src/`, writes `research/codebase-research.md` |
| **Research Verifier** | sonnet | Fact-checks every `file:line` reference in the research, grades quality (HIGH / MEDIUM / LOW), writes `research/verified-research.md` |
| **Bug Fixer** | haiku | Applies the implementation plan, runs `make test`, writes `fix-summary.md` |
| **Security Verifier** | opus | Scans changed files for injection, secrets, insecure comparisons, and missing validation; writes `security-report.md` |
| **Unit Test Generator** | sonnet | Generates Jest + supertest tests for changed code following FIRST principles, runs `make test`, writes `test-report.md` |

**opus** is used for the Security Verifier because security review demands deep reasoning — finding subtle injection paths, timing-side-channels, and auth bypasses requires the strongest available model where cost is secondary to thoroughness. 

**sonnet** covers the Research Verifier and Unit Test Generator: both need solid comprehension and code generation but are well-defined tasks that don't require opus-level reasoning. 

**haiku** handles the Bug Fixer — the implementation plan already tells it exactly what to change, so the task is largely mechanical execution; the fastest and cheapest model with max effort may work here.

### How to run

The pipeline requires [Claude Code](https://docs.anthropic.com/en/docs/claude-code) (`claude` CLI) to be installed and authenticated.

```bash
./run-pipeline.sh
```

The script iterates over every directory under `context/bugs/`. A bug is skipped if its `test-report.md` already exists (i.e. it was already processed). Each bug goes through six sequential steps:

```
Codebase Research → Research Verification → Implementation Planning
  → Bug Fix → Security Review → Unit Test Generation
```

Artifacts written per bug:

| File | Written by |
|---|---|
| `context/bugs/XXX/research/codebase-research.md` | Researcher (Step 1) |
| `context/bugs/XXX/research/verified-research.md` | Research Verifier (Step 2) |
| `context/bugs/XXX/implementation-plan.md` | Planner (Step 3) |
| `context/bugs/XXX/fix-summary.md` | Bug Fixer (Step 4) |
| `context/bugs/XXX/security-report.md` | Security Verifier (Step 5) |
| `context/bugs/XXX/test-report.md` | Unit Test Generator (Step 6) |

The pipeline stops early if research quality is `LOW`, tests fail after a fix, or a `CRITICAL` security finding is detected.

---

## Running Tests

```bash
make test
```

Expected output:

```
PASS  tests/auth.test.js
  Auth API
    ✓ POST /api/register creates a new user
    ✓ GET /api/profile without token returns 401

Tests: 2 passed, 2 total
```

---

## Testing Routes with curl

Make sure the service is running (`make up`) before executing these.

### Register a user

```bash
curl -s -X POST http://localhost:3000/api/register \
  -H "Content-Type: application/json" \
  -d '{"username":"alice","email":"alice@example.com","password":"secret123"}' | jq
```

---

### Log in

```bash
curl -s -X POST http://localhost:3000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"alice@example.com","password":"secret123"}' | jq
```

---

### Get profile with a legitimate token

Copy the token returned from `/register` and use it as a Bearer token:

```bash
curl -s http://localhost:3000/api/profile \
  -H "Authorization: Bearer YOURTOKENHERE" | jq
```

---

### Missing or invalid token

```bash
curl -s http://localhost:3000/api/profile | jq
# → { "error": "Unauthorized" }  (401)
```
