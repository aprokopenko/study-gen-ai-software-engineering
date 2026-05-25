# Codebase Research — Bug 001

## Bug Summary

The `POST /register` handler creates a new user without first checking whether the supplied email is already registered. As a result, multiple user records can be created with the same email, each returning `HTTP 201`. The expected behaviour is that registering with an email already in use returns `HTTP 409 Conflict` with body `{ "error": "Email already in use" }`. The helper `findByEmail()` is imported into the route module but is never called inside the register handler.

## Relevant Files

| File | Purpose |
|------|---------|
| `src/routes/auth.js` | Defines the `POST /register` handler where the duplicate-email check is missing. |
| `src/store/users.js` | Provides `addUser` and `findByEmail`; the store performs no uniqueness enforcement. |

## Root Cause

The register handler in `src/routes/auth.js` validates that `username`, `email`, and `password` are present, then immediately calls `addUser(...)` without consulting the store for an existing record. `findByEmail` is imported on line 2 but only used by the login handler. Because neither the handler nor the store (`addUser` in `src/store/users.js`) enforces email uniqueness, every registration unconditionally pushes a new record and returns `201`. The defect is the absence of a guard between input validation and user creation.

## Evidence

### src/routes/auth.js:2

```js
const { addUser, findByEmail } = require('../store/users');
```

`findByEmail` is imported but, within the register handler, never invoked — confirming the bug report's claim.

### src/routes/auth.js:8-16

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

After the field-presence check, the handler calls `addUser` directly with no duplicate-email lookup, so a repeated email silently creates another record and returns `201`.

### src/store/users.js:5-9

```js
function addUser({ username, email, password }) {
  const user = { id: nextId++, username, email, password };
  users.push(user);
  return user;
}
```

`addUser` unconditionally pushes a new user; the store enforces no uniqueness, so the only place a duplicate check can live is the route handler.

### src/store/users.js:11-13

```js
function findByEmail(email) {
  return users.find((u) => u.email === email);
}
```

`findByEmail` returns the matching user or `undefined`, exactly the primitive needed to detect a duplicate before calling `addUser`.

## Scope of Impact

Only the `POST /register` flow in `src/routes/auth.js` is affected. The duplicate records it creates degrade the `POST /login` flow indirectly: `findByEmail` returns the first matching record, so a second user registering under an existing email could never authenticate as themselves. No other routes call `addUser`. Edge cases to consider in the fix: email comparison is currently exact/case-sensitive, so the duplicate check should use the same `findByEmail` semantics to stay consistent with login.

## References

- `src/routes/auth.js`
- `src/store/users.js`
- `context/bugs/001/bug-context.md`
