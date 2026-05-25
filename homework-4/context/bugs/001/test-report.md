# Test Report — Bug 001

## Summary

9 new tests were generated in `tests/auth.duplicate-email.test.js`, covering the duplicate-email guard introduced in the Bug 001 fix to `src/routes/auth.js`. All 9 generated tests plus the 2 pre-existing tests in `tests/auth.test.js` passed (11 total across 2 suites). The run was confirmed by re-executing `make test` from the orchestrator.

## Generated Tests

### tests/auth.duplicate-email.test.js — registers a new user and returns 201 with correct body shape

**Covers:** The happy path is not broken by the new guard — a fresh registration still returns HTTP 201 with `user.id`, `user.username`, `user.email`, and a `token`, and does not leak `password` in the response.

---

### tests/auth.duplicate-email.test.js — returns 409 when the same email is registered a second time

**Covers:** The core fix — a second `POST /api/register` with an already-registered email returns HTTP 409 with body `{ "error": "Email already in use" }`.

---

### tests/auth.duplicate-email.test.js — returns 409 even when only the email matches (username differs)

**Covers:** The conflict check is keyed on email alone; a different username with the same email is still rejected with 409.

---

### tests/auth.duplicate-email.test.js — allows a second registration with a different email (returns 201)

**Covers:** The uniqueness guard does not block registrations for unrelated email addresses.

---

### tests/auth.duplicate-email.test.js — returns 400 when email field is missing (validation runs before duplicate check)

**Covers:** The pre-existing field-presence guard (400) still fires before the new duplicate-email guard; insertion order of the two guards is correct.

---

### tests/auth.duplicate-email.test.js — returns 400 when username field is missing

**Covers:** Field-presence validation still catches a missing `username`.

---

### tests/auth.duplicate-email.test.js — returns 400 when password field is missing

**Covers:** Field-presence validation still catches a missing `password`.

---

### tests/auth.duplicate-email.test.js — treats email addresses as case-sensitive (different case is not a duplicate)

**Covers:** `findByEmail` uses strict equality; the fix preserves the existing case-sensitive semantics, so `Alice@example.com` is not treated as a duplicate of `alice@example.com`.

---

### tests/auth.duplicate-email.test.js — continues to return 409 on every subsequent attempt with the same email

**Covers:** The duplicate guard is idempotent — a third (and beyond) attempt with the same email continues to be rejected with 409, confirming no state corruption on repeated rejections.

---

## Test Run Output

```
docker compose run --rm app npm test

> user-registration-service@1.0.0 test
> jest --forceExit

PASS tests/auth.test.js
PASS tests/auth.duplicate-email.test.js

Test Suites: 2 passed, 2 total
Tests:       11 passed, 11 total
Snapshots:   0 total
Time:        0.804 s
Ran all test suites.
```

## References

- `context/bugs/001/fix-summary.md`
- `context/bugs/001/research/codebase-research.md`
- `src/routes/auth.js`
- `src/store/users.js`
- `tests/auth.test.js` (pre-existing, not modified)
- `tests/auth.duplicate-email.test.js` (generated)
