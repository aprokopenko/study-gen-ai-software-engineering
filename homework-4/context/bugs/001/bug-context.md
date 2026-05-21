# Bug 001 — Duplicate Email Registration

## Location
- **File:** `src/routes/auth.js`
- **Handler:** `POST /register`

## Problem
The register handler does not check whether an email address already exists in the store before creating a new user. `findByEmail()` is imported but never called inside the register handler.

## Expected Behavior
If a user attempts to register with an email that is already in use, the API should return:
```
HTTP 409 Conflict
{ "error": "Email already in use" }
```

## Actual Behavior
A second (and third, fourth…) user record is silently created with the same email address, returning `HTTP 201` each time.
