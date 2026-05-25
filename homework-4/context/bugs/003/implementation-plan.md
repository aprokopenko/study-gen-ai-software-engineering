# Implementation Plan — Bug 003

## Objective

Make auth tokens unforgeable by signing them with `API_SECRET` in `generateToken` and rejecting any token whose signature does not verify in `authMiddleware`, so a base64(email) token crafted by an attacker yields HTTP 401.

## Changes

<!-- One block per location to be modified. -->

### src/middleware/auth.js

**Location:** line 3 — module-level constant `API_SECRET`

**Before:**

```js
const API_SECRET = 'super-secret-key-123';
```

**Fix intent:**

Source the signing secret from the environment (`process.env.API_SECRET`) instead of a hardcoded literal so the real secret is not committed in source, and bring in Node's built-in `crypto` module (no new dependency) to compute HMAC signatures used by the two functions below.

---

### src/middleware/auth.js

**Location:** lines 5–7 — function `generateToken`

**Before:**

```js
function generateToken(user) {
  return Buffer.from(user.email).toString('base64');
}
```

**Fix intent:**

Produce a token that contains both the email payload and an HMAC-SHA256 signature of that payload keyed by `API_SECRET` (e.g. a `payload.signature` structure), so the token carries proof of server issuance that an attacker cannot reproduce without the secret; this directly removes the forgeability root cause.

---

### src/middleware/auth.js

**Location:** lines 15–26 — function `authMiddleware` (token parsing through user lookup)

**Before:**

```js
  const token = authHeader.slice(7); // strip "Bearer "
  let email;
  try {
    email = Buffer.from(token, 'base64').toString('utf8');
  } catch {
    return res.status(401).json({ error: 'Unauthorized' });
  }

  const user = findByEmail(email);
  if (!user) {
    return res.status(401).json({ error: 'Unauthorized' });
  }
```

**Fix intent:**

Before trusting the decoded email, split the token into payload and signature, recompute the expected HMAC over the payload with `API_SECRET`, and compare using a constant-time comparison (`crypto.timingSafeEqual`); reject with HTTP 401 if the token is malformed or the signature does not match, so only server-issued tokens reach the `findByEmail` lookup.

---

## Verification

After applying all changes, run `make test`. Record the full output in `fix-summary.md`.

## References

- context/bugs/003/research/codebase-research.md
- src/middleware/auth.js
