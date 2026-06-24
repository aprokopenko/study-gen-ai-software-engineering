# User Registration Micro-Service Specification

> Ingest the information from this file, implement the Low-Level Tasks, and generate the code that will satisfy the High and Mid-Level Objectives.

## High-Level Objective
- Build a minimal User Registration micro-service (REST API) with intentional bugs and a security vulnerability, containerized with Docker, orchestrated via Makefile.

## Mid-Level Objectives
- The API exposes POST /register, POST /login, GET /profile endpoints with in-memory user storage
- The app contains exactly 2 intentional logic bugs (duplicate email allowed, broken password comparison) and 1 security issue (plaintext password storage + forgeable auth token)
- The app runs inside a Docker container with docker-compose, all commands accessible via Makefile
- A minimal passing test suite exists (`npm test` works) so the pipeline's Bug Fixer can run tests after changes
- Bug context documentation exists in `context/bugs/` describing each seeded defect for the pipeline to consume

## Implementation Notes
- Stack: Node.js 22 (LTS) + Express.js + Jest + supertest
- All execution happens inside Docker — no local Node.js required
- Local volume mounts for src/, tests/, package.json — no rebuild needed on code changes
- Makefile targets: `make build`, `make up`, `make down`, `make test`, `make lint`, `make shell`
- In-memory storage only (no database) — a simple array in a module
- Auth token is intentionally just base64(email) — trivially forgeable (this is the security issue)
- Keep total app code under 150 lines for agent readability
- Plain JS with CommonJS (require/module.exports), no TypeScript
- Security considerations: intentionally weak — this is the "before" state for the pipeline

## Context

### Beginning context
- Empty project directory
- TASKS.md with homework requirements
- No node_modules, no package.json, no Docker files

### Ending context
- `src/app.js` — Express app setup (exported for testing)
- `src/index.js` — Server entry point
- `src/routes/auth.js` — Register/login/profile route handlers (contains bugs)
- `src/store/users.js` — In-memory user store
- `src/middleware/auth.js` — Auth middleware (contains security issue)
- `tests/auth.test.js` — Minimal test suite (passes even with bugs present)
- `package.json` — Dependencies and scripts
- `Dockerfile` — Node 22 Alpine image
- `docker-compose.yml` — Service definition with local volume mounts
- `Makefile` — Orchestration commands
- `context/bugs/001/bug-context.md` — Duplicate email bug documentation
- `context/bugs/002/bug-context.md` — Password comparison bug documentation
- `context/bugs/003/bug-context.md` — Security issue documentation
- `.env.example` — Shows what env vars should be used
- `.dockerignore` — Keeps image clean

## Low-Level Tasks

### 1. Create package.json and project directories

Create package.json with Express, Jest, and supertest dependencies. Add scripts for start and test. Create the folder structure for src/, tests/, context/bugs/.

**Files:** `package.json`

**Details:**
```json
{
  "name": "user-registration-service",
  "version": "1.0.0",
  "scripts": {
    "start": "node src/index.js",
    "test": "jest --forceExit"
  },
  "dependencies": {
    "express": "^4.21"
  },
  "devDependencies": {
    "jest": "^29",
    "supertest": "^7"
  },
  "jest": {
    "testEnvironment": "node"
  }
}
```

**Verify:**
- `cat package.json | python3 -c "import sys,json;json.load(sys.stdin);print('valid')"` prints "valid"
- Directories `src/`, `tests/`, `context/bugs/` exist

---

### 2. Create Dockerfile, .dockerignore, and docker-compose.yml

Create the Docker infrastructure so all subsequent tasks can be verified by running code inside the container. Use Node 22 Alpine. Mount local src/, tests/, package.json as volumes so no rebuild is needed on code changes.

**Files:** `Dockerfile`, `.dockerignore`, `docker-compose.yml`

**Details:**
```dockerfile
FROM node:22-alpine
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
EXPOSE 3000
CMD ["node", "src/index.js"]
```

```dockerignore
node_modules
.git
.env
docs/
context/
```

```yaml
services:
  app:
    build: .
    ports:
      - "3000:3000"
    volumes:
      - ./src:/app/src
      - ./tests:/app/tests
      - ./package.json:/app/package.json
      - node_modules:/app/node_modules
    environment:
      - NODE_ENV=development
      - PORT=3000
    restart: unless-stopped

volumes:
  node_modules:
```

**Verify:**
- `docker compose build` completes without errors
- `docker compose run --rm app node -e "console.log('ok')"` prints "ok"
- `docker compose run --rm app node -e "require('express')"` exits with code 0 (express installed)

---

### 3. Create Makefile

Create a Makefile with targets for all Docker operations. This is the single interface for running the app and tests. All subsequent verification steps use `make` commands.

**Files:** `Makefile`

**Details:**
```makefile
.PHONY: build up down test lint shell logs restart install

build:
	docker compose build

up:
	docker compose up -d

down:
	docker compose down

test:
	docker compose run --rm app npm test

lint:
	docker compose run --rm app npx eslint src/

shell:
	docker compose run --rm app sh

logs:
	docker compose logs -f

restart: down up

install:
	docker compose run --rm app npm install
```

**Verify:**
- `make build` completes without errors (exit code 0)
- `make shell` opens a shell inside the container (then exit)

---

### 4. Create in-memory user store

Create a simple in-memory user store module that holds users in an array and exposes addUser, findByEmail, getAllUsers, clearUsers functions. Passwords are stored as plaintext intentionally (security issue).

**Files:** `src/store/users.js`

**Functions:** `addUser`, `findByEmail`, `getAllUsers`, `clearUsers`

**Details:**
- Store: array of `{ id, username, email, password }`
- `addUser(userData)` — pushes to array, auto-increments id, returns created user
- `findByEmail(email)` — returns user object or undefined
- `getAllUsers()` — returns full array
- `clearUsers()` — resets array (for tests)
- Passwords stored as plaintext — no hashing (intentional security issue)

**Verify:**
- File `src/store/users.js` exists and exports `addUser`, `findByEmail`, `getAllUsers`, `clearUsers`
- No `bcrypt`, `crypto.hash`, or similar hashing logic present in the file (plaintext confirmed)
- Full verification deferred to Task 8 (`make test`) and Task 9 (integration)

---

### 5. Create auth middleware with forgeable token

Create authentication middleware that extracts a Bearer token from Authorization header, base64-decodes it to get email, and attaches user to request. Include a hardcoded API_SECRET constant that is never used (dead code security finding).

**Files:** `src/middleware/auth.js`

**Functions:** `authMiddleware`, `generateToken`

**Details:**
```js
// Intentionally insecure — no signing, no expiry
const API_SECRET = 'super-secret-key-123'; // hardcoded, never used

function generateToken(user) {
  return Buffer.from(user.email).toString('base64');
}

function authMiddleware(req, res, next) {
  // Extract "Bearer <token>" from Authorization header
  // base64-decode token to get email
  // Look up user in store by email
  // If not found or no header: return 401 { error: "Unauthorized" }
  // Attach user to req.user, call next()
}
```

**Verify:**
- File `src/middleware/auth.js` exists and exports `authMiddleware`, `generateToken`
- `grep "API_SECRET" src/middleware/auth.js` — shows the hardcoded secret in source
- `grep "base64" src/middleware/auth.js` — confirms base64 encoding (no JWT/signing)
- Full verification deferred to Task 8 (`make test`) and Task 9 (integration)

---

### 6. Create route handlers with seeded bugs

Create Express router with POST /register, POST /login, GET /profile. Seed two bugs: (1) no duplicate email check on register, (2) typo in property name on login password comparison.

**Files:** `src/routes/auth.js`

**Functions:** `router.post('/register')`, `router.post('/login')`, `router.get('/profile')`

**Details:**
```js
// POST /register
// BUG: does NOT check if email already exists — allows duplicates
router.post('/register', (req, res) => {
  const { username, email, password } = req.body;
  if (!username || !email || !password) return res.status(400).json({ error: 'All fields required' });
  // Missing: const existing = findByEmail(email); if (existing) return 409
  const user = addUser({ username, email, password });
  const token = generateToken(user);
  res.status(201).json({ user: { id: user.id, username, email }, token });
});

// POST /login
// BUG: typo "user.pasword" instead of "user.password" — always undefined, login always fails
router.post('/login', (req, res) => {
  const { email, password } = req.body;
  const user = findByEmail(email);
  if (!user || password !== user.pasword) { // <-- typo: "pasword"
    return res.status(401).json({ error: 'Invalid credentials' });
  }
  const token = generateToken(user);
  res.status(200).json({ token });
});

// GET /profile — protected by authMiddleware
router.get('/profile', authMiddleware, (req, res) => {
  const { password, ...userData } = req.user;
  res.json(userData);
});
```

**Verify:**
- File `src/routes/auth.js` exists and exports an Express router
- `grep "pasword" src/routes/auth.js` — shows the typo exists (bug 002)
- `grep -c "findByEmail" src/routes/auth.js` — count is 1 (only in login, NOT before addUser in register — bug 001)
- Full verification deferred to Task 8 (`make test`) and Task 9 (integration)

---

### 7. Create Express app and server entry point

Create app.js that sets up Express with JSON body parsing and mounts auth routes at /api. Create index.js that imports app and starts listening. Keep them separate so supertest can import app without starting the server.

**Files:** `src/app.js`, `src/index.js`

**Details:**
```js
// src/app.js
const express = require('express');
const authRoutes = require('./routes/auth');
const app = express();
app.use(express.json());
app.use('/api', authRoutes);
module.exports = app;

// src/index.js
const app = require('./app');
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => console.log(`Server running on port ${PORT}`));
```

**Verify:**
- Files `src/app.js` and `src/index.js` exist
- `src/app.js` contains `module.exports` (exports app for testing)
- `src/index.js` contains `app.listen` (starts server)
- Full verification deferred to Task 8 (`make test`) and Task 9 (integration)

---

### 8. Create minimal test suite

Create a Jest test file with basic happy-path tests that PASS even with the bugs present. Do not test duplicate email or login — those are for the Unit Test Generator agent to add later.

**Files:** `tests/auth.test.js`

**Details:**
```js
const request = require('supertest');
const app = require('../src/app');
const { clearUsers } = require('../src/store/users');

beforeEach(() => clearUsers());

describe('Auth API', () => {
  test('POST /api/register creates a new user', async () => {
    const res = await request(app)
      .post('/api/register')
      .send({ username: 'john', email: 'john@test.com', password: 'pass123' });
    expect(res.status).toBe(201);
    expect(res.body.user.email).toBe('john@test.com');
    expect(res.body.token).toBeDefined();
  });

  test('GET /api/profile without token returns 401', async () => {
    const res = await request(app).get('/api/profile');
    expect(res.status).toBe(401);
  });
});
```

**Verify:**
- `make test` exits with code 0 and shows "2 passed"
- Output does NOT contain "login" or "duplicate" test names (those scenarios are untested)

---

### 9. Verify seeded bugs via HTTP (integration check)

Start the app and confirm both bugs and the security issue are demonstrable via HTTP requests.

**Files:** none (verification-only task)

**Verify:**
- `make up` starts the service
- Register: `curl -s -X POST http://localhost:3000/api/register -H "Content-Type: application/json" -d '{"username":"a","email":"a@t.com","password":"123"}'` — returns 201
- Duplicate: same curl again — returns 201 again (bug 001 confirmed)
- Login: `curl -s -X POST http://localhost:3000/api/login -H "Content-Type: application/json" -d '{"email":"a@t.com","password":"123"}'` — returns 401 (bug 002 confirmed)
- Forged token: `curl -s http://localhost:3000/api/profile -H "Authorization: Bearer $(echo -n 'a@t.com' | base64)"` — returns 200 with user data (security issue confirmed — token forged without login)
- `make down`

---

### 10. Create bug context documentation

Create bug context markdown files documenting each intentional defect. These files are consumed by the Bug Researcher agent in the pipeline.

**Files:** `context/bugs/001/bug-context.md`, `context/bugs/002/bug-context.md`, `context/bugs/003/bug-context.md`

**Details:**

**001 — Duplicate email registration:**
- File: `src/routes/auth.js`, POST /register handler
- Problem: No call to `findByEmail()` before creating user
- Expected: Return 409 Conflict if email exists
- Actual: Silently creates duplicate user entries
- Impact: Data integrity, users can't reliably log in

**002 — Login always fails (typo):**
- File: `src/routes/auth.js`, POST /login handler
- Problem: `user.pasword` (missing 's') is always undefined
- Expected: `password !== user.password` comparison works
- Actual: Always returns 401 Invalid credentials
- Impact: No user can log in

**003 — Security: plaintext passwords + forgeable token:**
- Files: `src/store/users.js`, `src/middleware/auth.js`
- Problems:
  - Passwords stored without hashing (plaintext in memory)
  - Token is `base64(email)` — no signature, trivially forgeable
  - Hardcoded `API_SECRET = 'super-secret-key-123'` in source (dead code)
- Impact: Any attacker who knows an email can forge auth; password leak if store is exposed

**Verify:**
- All three files exist: `context/bugs/001/bug-context.md`, `context/bugs/002/bug-context.md`, `context/bugs/003/bug-context.md`
- Each file references real file paths that exist in the project
- Bug descriptions match the defects confirmed in Task 9

---

### 11. Create .env.example

Create .env.example showing the environment variables that should be configured properly (demonstrating what the hardcoded values should have been).

**Files:** `.env.example`

**Details:**
```env
PORT=3000
API_SECRET=change-me-to-a-real-secret
NODE_ENV=development
```

**Verify:**
- `.env.example` exists in project root
- Contains PORT, API_SECRET, NODE_ENV variables
- API_SECRET value is a placeholder (not the hardcoded secret from source code)
