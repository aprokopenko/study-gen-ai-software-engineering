# User Registration Service

## Service Overview

A minimal REST API for user registration and authentication. Single Node.js process, in-memory storage, no external dependencies beyond Express.

- **Runtime:** Node.js 22 (CommonJS, `require`/`module.exports`)
- **Framework:** Express 4
- **Port:** `process.env.PORT` or `3000`
- **Storage:** In-memory array (no database, no persistence between restarts)
- **Auth:** Bearer token in `Authorization` header

---

## Project Structure

```
src/
├── app.js              # Express instance — imported by tests and index.js
├── index.js            # Entry point — calls app.listen()
├── routes/
│   └── auth.js         # All route handlers: register, login, profile
├── store/
│   └── users.js        # In-memory user store
└── middleware/
    └── auth.js         # Token generation and auth middleware

tests/
└── auth.test.js        # Jest + supertest test suite

context/bugs/           # Known issue descriptions (read-only reference)
├── 001/bug-context.md
├── 002/bug-context.md
└── 003/bug-context.md
```

---

## API Endpoints

All routes are mounted under `/api`.

### `POST /api/register`
Creates a new user account.

**Request body:**
```json
{ "username": "string", "email": "string", "password": "string" }
```

**Responses:**
| Status | Body | Condition |
|---|---|---|
| 201 | `{ user: { id, username, email }, token }` | Success |
| 400 | `{ error: "All fields required" }` | Missing field |

### `POST /api/login`
Authenticates an existing user.

**Request body:**
```json
{ "email": "string", "password": "string" }
```

**Responses:**
| Status | Body | Condition |
|---|---|---|
| 200 | `{ token }` | Credentials valid |
| 401 | `{ error: "Invalid credentials" }` | User not found or wrong password |

### `GET /api/profile`
Returns the authenticated user's profile. Requires `Authorization: Bearer <token>` header.

**Responses:**
| Status | Body | Condition |
|---|---|---|
| 200 | `{ id, username, email }` | Valid token |
| 401 | `{ error: "Unauthorized" }` | Missing or invalid token |

---

## Running the Service

```bash
make build    # build Docker image (copies .env.example → .env if absent)
make up       # start on port 3000
make down     # stop
make test     # run Jest suite inside container
make shell    # open shell inside container
```

All commands run inside Docker. No local Node.js required.

---

## Testing

Framework: **Jest 29** + **supertest 7**

```bash
make test
```

Test file: `tests/auth.test.js`

---

## Conventions

- **CommonJS only** — use `require`/`module.exports`, no `import`/`export`
- **No TypeScript** — plain `.js` files throughout
- **No database** — all state is in the `users` array in `src/store/users.js`; it resets on every container restart
- **Environment variables** — `PORT` and `API_SECRET` read from `.env` (see `.env.example`)
- **Error responses** always use shape `{ error: "message" }` with an appropriate HTTP status code
- **`password` field** is never returned in API responses — stripped before sending (see profile handler)
- **Test isolation** — always call `clearUsers()` in `beforeEach` when writing new tests
