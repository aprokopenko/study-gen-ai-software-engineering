# Bug 003 — Security: Plaintext Passwords + Forgeable Auth Token

## Location
- **File:** `src/store/users.js` — password storage
- **File:** `src/middleware/auth.js` — token generation and verification

## Problems

### 3a. Plaintext password storage
Passwords are stored directly in the in-memory array with no hashing:
```js
const user = { id: nextId++, username, email, password }; // plaintext
```
Any process or agent that can read the store gets all passwords in cleartext.

### 3b. Forgeable auth token (no signature)
The auth token is simply `base64(email)`:
```js
function generateToken(user) {
  return Buffer.from(user.email).toString('base64');
}
```
An attacker who knows (or guesses) any registered email address can craft a valid token without ever logging in:
```bash
curl http://localhost:3000/api/profile \
  -H "Authorization: Bearer $(echo -n 'victim@example.com' | base64)"
# → 200 OK with full profile
```

### 3c. Hardcoded secret (dead code)
```js
const API_SECRET = 'super-secret-key-123'; // hardcoded, never used
```
The constant exists in source but is never applied. Even if a developer intended to use HMAC signing, it was never wired up.

## Fix
- Hash passwords with `bcrypt` (or `argon2`) before storing.
- Replace the base64 token with a signed JWT using `jsonwebtoken` and a secret loaded from an environment variable (not hardcoded).
- Remove or actually use `API_SECRET` — load it from `process.env.API_SECRET`.

## Impact
- **Confidentiality:** Password leak if in-memory store is exposed (e.g., via a debug endpoint or memory dump).
- **Authentication bypass:** Any email-knowledgeable attacker can impersonate any user without knowing their password.
- **Credential stuffing:** Plaintext passwords make credential reuse attacks trivial if the store is ever serialized/logged.
