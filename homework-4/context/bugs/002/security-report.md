# Security Report — 002

## Summary

Reviewed the single changed file `src/routes/auth.js` (the `POST /login` handler at line 25), as listed under `## Changes Made` in the fix summary. The fix corrects a misspelled property (`user.pasword` -> `user.password`) so the password comparison now reads the real stored value, which is a security improvement (the old typo made the stored side always `undefined`, allowing an auth bypass for any account with a falsy submitted password). Reviewing the corrected comparison and its immediate context surfaced 3 findings; highest severity is MEDIUM. No CRITICAL or HIGH issues were introduced by the fix itself.

## Findings

### MEDIUM: Timing-unsafe password comparison

**File:** `src/routes/auth.js:25`
**Description:** The fixed line compares the submitted password to the stored value with `password !== user.password`, a non-constant-time string equality that can leak information via response timing and aids credential brute-forcing; the comparison also implies passwords are stored and compared in plaintext rather than as a salted hash.
**Remediation:** Store password hashes (e.g. bcrypt/argon2) and verify with the library's constant-time compare function instead of `!==`.

### LOW: Missing input validation on login fields

**File:** `src/routes/auth.js:23`
**Description:** The login handler destructures `email` and `password` from `req.body` without validating presence or type, so a non-string `password` (e.g. an object or array) is passed directly into the comparison, allowing malformed input to reach authentication logic.
**Remediation:** Validate that `email` and `password` are present and are strings before performing the lookup and comparison.

### INFO: Generic credential error message (defence in depth)

**File:** `src/routes/auth.js:26`
**Description:** The handler returns the same `Invalid credentials` message for both unknown user and wrong password, which is good practice; noting it as a positive observation since the surrounding change touches this response path.
**Remediation:** No action required; keep the unified error response to avoid user enumeration.

---

## References

- context/bugs/002/fix-summary.md
- src/routes/auth.js
