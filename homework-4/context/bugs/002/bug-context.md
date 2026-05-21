# Bug 002 — Login Always Fails

## Location
- **File:** `src/routes/auth.js`
- **Handler:** `POST /login`

## Problem
Every login attempt return 401 Invalid credentials regardless of whether the credentials are correct.

## Expected Behavior
```
POST /api/login  { "email": "a@t.com", "password": "123" }
→ HTTP 200  { "token": "<token>" }
```

## Actual Behavior
```
POST /api/login  { "email": "a@t.com", "password": "123" }
→ HTTP 401  { "error": "Invalid credentials" }
```

## Steps to Reproduce
1. Register a user via `POST /api/register` with any password
2. Attempt to login via `POST /api/login` with the same credentials
3. Always receives 401 regardless of correct password
