# Fix Summary — Bug 001

## Status

Overall Status: PASSED

## Changes Made

### src/routes/auth.js

**Location:** lines 13-15 — handler `router.post('/register', ...)`

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

**After:**
```js
router.post('/register', (req, res) => {
  const { username, email, password } = req.body;
  if (!username || !email || !password) {
    return res.status(400).json({ error: 'All fields required' });
  }
  if (findByEmail(email)) {
    return res.status(409).json({ error: 'Email already in use' });
  }
  const user = addUser({ username, email, password });
  const token = generateToken(user);
  res.status(201).json({ user: { id: user.id, username, email }, token });
});
```

---

## Test Output

```
docker compose run --rm app npm test
 Container homework-4-app-run-755797b4840b Creating 
 Container homework-4-app-run-755797b4840b Created 

> user-registration-service@1.0.0 test
> jest --forceExit

PASS tests/auth.test.js
  Auth API
    ✓ POST /api/register creates a new user (40 ms)
    ✓ GET /api/profile without token returns 401 (4 ms)

Test Suites: 1 passed, 1 total
Tests:       2 passed, 2 total
Snapshots:   0 total
Time:        0.634 s
Ran all test suites.
Force exiting Jest: Have you considered using `--detectOpenHandles` to detect async operations that kept running after all tests finished?
```

## Manual Verification

To verify the fix works correctly, manually test that a POST request to `/api/register` with a duplicate email that already exists in the store returns HTTP 409 with the error message "Email already in use", while new email registrations continue to work normally.

## References

- context/bugs/001/implementation-plan.md
- src/routes/auth.js
