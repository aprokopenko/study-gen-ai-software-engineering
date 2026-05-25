# Codebase Research — Bug 002

## Bug Summary

Every login attempt to `POST /login` returns `401 Invalid credentials` regardless of whether the supplied credentials are correct. A user who registers successfully and then logs in with the exact same email and password is still rejected. The expected behaviour is that a matching email/password pair returns `HTTP 200` with a `{ "token": "<token>" }` body.

## Relevant Files

| File | Purpose |
|------|---------|
| `src/routes/auth.js` | Defines the `POST /login` handler containing the faulty password comparison. |
| `src/store/users.js` | In-memory user store; defines the canonical user object shape, confirming the stored field is `password`. |

## Root Cause

The defect is in the `POST /login` handler in `src/routes/auth.js:25`. The credential check reads `user.pasword` — a misspelling of `password` (missing the second `s`). The user objects created by `addUser` in `src/store/users.js:6` only have a `password` property, so `user.pasword` always evaluates to `undefined`. The comparison `password !== user.pasword` therefore reduces to `password !== undefined`, which is true for every real (non-undefined) password the client submits. As a result the guard always enters its error branch and returns `401 Invalid credentials`, no matter how correct the credentials are.

## Evidence

### `src/routes/auth.js:25`

```js
  if (!user || password !== user.pasword) {
    return res.status(401).json({ error: 'Invalid credentials' });
  }
```

The comparison references `user.pasword` (typo) instead of `user.password`; `user.pasword` is always `undefined`, so the condition is always true and the handler always returns 401.

### `src/store/users.js:5-8`

```js
function addUser({ username, email, password }) {
  const user = { id: nextId++, username, email, password };
  users.push(user);
  return user;
}
```

The stored user object has a `password` property (correctly spelled). There is no `pasword` property, confirming the login handler reads a field that does not exist.

## Scope of Impact

- Affects the `POST /login` route exclusively; every login request is impacted regardless of input.
- `POST /register` and `GET /profile` are unaffected — they do not reference the misspelled field.
- No edge cases produce a correct result: the only way `password !== user.pasword` could be false is if the client submitted an `undefined` password, which cannot be expressed as a valid login. Effectively login is 100% broken.

## References

- `src/routes/auth.js`
- `src/store/users.js`
