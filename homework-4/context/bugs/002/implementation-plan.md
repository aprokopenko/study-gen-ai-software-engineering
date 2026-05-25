# Implementation Plan — Bug 002

## Objective

Correct the misspelled property reference in the `POST /login` credential check so that login succeeds when the submitted password matches the stored password.

## Changes

### src/routes/auth.js

**Location:** line 25 — `POST /login` handler

**Before:**

```js
  if (!user || password !== user.pasword) {
    return res.status(401).json({ error: 'Invalid credentials' });
  }
```

**Fix intent:**

Change the property reference `user.pasword` to the correctly spelled `user.password` so the comparison reads the actual stored password field; this makes the guard reject only genuinely mismatched credentials instead of always evaluating true against `undefined`.

---

## Verification

After applying all changes, run `make test`. Record the full output in `fix-summary.md`.

## References

- context/bugs/002/research/codebase-research.md
- src/routes/auth.js
