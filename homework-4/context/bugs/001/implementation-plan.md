# Implementation Plan — Bug 001

## Objective

Make `POST /register` reject an email that is already registered by returning `HTTP 409` with `{ "error": "Email already in use" }` instead of silently creating a duplicate user.

## Changes

### src/routes/auth.js

**Location:** lines 8-16 — handler `router.post('/register', ...)`

**Before:**

```js
router.post('/register', (req, res) => {
  const { username, email, password } = req.body;
  if (!username || !email || !password) {
    return res.status(400).json({ error: 'All fields required' });
  }
  const user = addUser({ username, email, password });
  const token = generateToken(user);
  res.status(201).json({ user: { id: user.id, username, email }, token });
});
```

**Fix intent:**

After the field-presence validation and before calling `addUser`, add a guard that calls the already-imported `findByEmail(email)`; if it returns a truthy user, respond with `res.status(409).json({ error: 'Email already in use' })` and return. This places the uniqueness check at the only viable location (the store enforces none) and uses the same lookup semantics as the login flow, resolving the root cause that `findByEmail` was imported but never invoked in the register handler.

---

## Verification

After applying all changes, run `make test`. Record the full output in `fix-summary.md`.

## References

- context/bugs/001/research/codebase-research.md
- src/routes/auth.js
