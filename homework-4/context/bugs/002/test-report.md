# Test Report — 002

## Summary

9 tests were generated in `tests/auth.login.test.js` covering the Bug 002 fix: the one-character typo correction in `src/routes/auth.js` line 25 (`user.pasword` → `user.password`). All pre-existing tests continued to pass, bringing the full suite to 20 tests across 3 suites with a clean exit.

## Generated Tests

### tests/auth.login.test.js — returns 200 when email and password are both correct

**Covers:** The core broken behaviour — a registered user can now successfully log in with correct credentials and receive HTTP 200.

---

### tests/auth.login.test.js — returns a token in the response body on successful login

**Covers:** The 200 response body includes a `token` property; token presence is asserted (not its value) to remain Repeatable across runs.

---

### tests/auth.login.test.js — login response does not include the stored password

**Covers:** The login success response does not leak the user's stored password field in the JSON body.

---

### tests/auth.login.test.js — same credentials work on a second consecutive login (store not mutated by login)

**Covers:** A login operation does not mutate the in-memory user record, so the same credentials remain valid for subsequent login attempts.

---

### tests/auth.login.test.js — returns 401 when the password is wrong

**Covers:** The guard still rejects a correct email paired with an incorrect password, returning 401 with the `Invalid credentials` error body.

---

### tests/auth.login.test.js — returns 401 when the password differs only by case

**Covers:** Password comparison is strict/case-sensitive; a password that differs only by letter casing is correctly rejected with 401.

---

### tests/auth.login.test.js — returns 401 when the email is not registered

**Covers:** An email that was never registered causes `findByEmail` to return `undefined`, which the guard correctly rejects with 401.

---

### tests/auth.login.test.js — returns 401 when the email field is omitted

**Covers:** A login request body missing the email field results in 401 (findByEmail(undefined) returns undefined, guard fires).

---

### tests/auth.login.test.js — returns 401 when the password field is omitted

**Covers:** A login request body missing the password field results in 401 (undefined !== stored password, guard fires).

---

## Test Run Output

```
docker compose run --rm app npm test
 Container homework-4-app-run-cfa4e80b5c94 Creating
 Container homework-4-app-run-cfa4e80b5c94 Created

> user-registration-service@1.0.0 test
> jest --forceExit

PASS tests/auth.test.js
PASS tests/auth.duplicate-email.test.js
PASS tests/auth.login.test.js

Test Suites: 3 passed, 3 total
Tests:       20 passed, 20 total
Snapshots:   0 total
Time:        0.83 s
Ran all test suites.
Force exiting Jest: Have you considered using `--detectOpenHandles` to detect async operations that kept running after all tests finished?
```

## References

- context/bugs/002/fix-summary.md
- context/bugs/002/research/codebase-research.md
- src/routes/auth.js
- src/store/users.js
- src/middleware/auth.js
- tests/auth.login.test.js
