# Codebase Research — Bug 003

## Bug Summary

The authentication token is generated as a bare `base64(user.email)` with no cryptographic signature, no secret, and no expiry. `authMiddleware` simply base64-decodes any presented Bearer token to recover an email and looks that email up in the user store — it never verifies the token was actually issued by the server. As a result, anyone can forge a valid token for any registered user by base64-encoding their email, and `GET /api/profile` returns that user's profile with HTTP 200. Expected behaviour is that a forged (unsigned) token is rejected with HTTP 401 `{ "error": "Unauthorized" }`.

## Relevant Files

| File | Purpose |
|------|---------|
| `src/middleware/auth.js` | Defines `generateToken` (issues the forgeable token) and `authMiddleware` (accepts it without verification); also holds the unused `API_SECRET`. |
| `src/routes/auth.js` | Calls `generateToken` on register/login and protects `GET /profile` with `authMiddleware` — the consumers of the broken token. |
| `src/store/users.js` | In-memory user store; `findByEmail` is how the middleware resolves a token's email to a full user record. |
| `.env.example` | Declares `API_SECRET` as the intended signing secret (currently never loaded or used by the code). |

## Root Cause

`generateToken` encodes only the email and applies no signature, so the token carries no proof of server issuance:

The token is fully reconstructible by an attacker who knows a victim's email (`base64(email)`). `authMiddleware` then trusts the decoded email unconditionally — its only checks are "is there a Bearer prefix", "does base64 decode", and "does a user with this email exist". None of these distinguish a server-issued token from an attacker-crafted one, because there is nothing in the token to verify against the secret. The hardcoded `API_SECRET` constant exists but is dead code — it is never incorporated into either signing or verification.

## Evidence

<!-- One entry per relevant code location. Quote the exact lines from the file. -->

### src/middleware/auth.js:5

```js
function generateToken(user) {
  return Buffer.from(user.email).toString('base64');
}
```

The token is just base64 of the email — no HMAC/signature and no expiry, so it is trivially forgeable and the output is fully predictable from a known email.

### src/middleware/auth.js:15

```js
  const token = authHeader.slice(7); // strip "Bearer "
  let email;
  try {
    email = Buffer.from(token, 'base64').toString('utf8');
  } catch {
    return res.status(401).json({ error: 'Unauthorized' });
  }
```

The middleware extracts an email by base64-decoding whatever token is supplied; there is no integrity check tying the token to the server secret.

### src/middleware/auth.js:23

```js
  const user = findByEmail(email);
  if (!user) {
    return res.status(401).json({ error: 'Unauthorized' });
  }

  req.user = user;
```

Authorization is granted purely on "does a user with this decoded email exist", so any forged token naming a real user passes.

### src/middleware/auth.js:3

```js
const API_SECRET = 'super-secret-key-123';
```

The intended signing secret is hardcoded and entirely unused (dead code) — it is never referenced by `generateToken` or `authMiddleware`.

### src/routes/auth.js:33

```js
router.get('/profile', authMiddleware, (req, res) => {
  const { password, ...userData } = req.user;
  res.json(userData);
});
```

The only protected route; it returns the full user record (minus password) for whichever user the forged token resolves to, confirming the data-exposure impact.

## Scope of Impact

- **Direct:** `GET /api/profile` (the only route guarded by `authMiddleware`) is fully bypassable — any registered user's profile can be read with a forged token. Any future route protected by `authMiddleware` inherits the same vulnerability.
- **Token issuance:** Both `POST /api/register` (`src/routes/auth.js:17`) and `POST /api/login` (`src/routes/auth.js:28`) call `generateToken`, so every issued token is currently of the forgeable form; fixing `generateToken` must remain backward-compatible with what `authMiddleware` expects.
- **Edge cases:** Tokens have no expiry, so even a legitimately issued token never becomes invalid. The secret is hardcoded in source (`API_SECRET`) rather than read from `.env`, so it is also exposed in the repository.

## References

<!-- Deduplicated list of every file read during research -->

- `src/middleware/auth.js`
- `src/routes/auth.js`
- `src/store/users.js`
- `src/index.js`
- `.env.example`
- `package.json`
- `context/bugs/003/bug-context.md`
