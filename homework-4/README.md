# User Registration Micro-Service

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
