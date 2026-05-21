# Bug 002 — Login Always Fails (Typo in Property Name)

## Location
- **File:** `src/routes/auth.js`
- **Handler:** `POST /login`

## Problem
A one-character typo causes the password comparison to always evaluate to `true` for the failure branch:

```js
// Buggy line (line ~24):
if (!user || password !== user.pasword) {   // "pasword" is missing an 's'
```

`user.pasword` is always `undefined`, so `password !== undefined` is always `true`, and every login attempt returns 401 Invalid credentials — even with the correct password.

## Expected Behavior
```
POST /api/login  { "email": "a@t.com", "password": "123" }
→ HTTP 200  { "token": "<base64-token>" }
```

## Actual Behavior
```
POST /api/login  { "email": "a@t.com", "password": "123" }
→ HTTP 401  { "error": "Invalid credentials" }
```

## Fix
Correct the property name:
```js
if (!user || password !== user.password) {
```

## Impact
- No user can log in through the `/login` endpoint.
- The only way to obtain a valid-looking token is via `/register` (which returns one directly) or by forging one (see Bug 003).
