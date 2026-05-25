# Security Report — 001

## Summary

Reviewed the single file changed by the fix, `src/routes/auth.js`, where a duplicate-email check (HTTP 409) was added to the `/register` handler. The fix itself introduces no new vulnerability: `findByEmail(email)` is a lookup against an in-memory store with no injection surface, and the change does not touch authentication, output encoding, or secret handling. Two pre-existing weaknesses sit in the immediate surrounding context (the `/login` handler directly adjacent to the change) and are noted below. Highest severity: HIGH.

## Findings

### HIGH: Plaintext password comparison with timing-unsafe equality

**File:** `src/routes/auth.js:25`
**Description:** The `/login` handler compares the submitted password directly against a stored value using `password !== user.pasword`, implying passwords are stored and compared in plaintext with a timing-unsafe string comparison; this exposes credentials on store compromise and is vulnerable to timing analysis. (Note: this line is adjacent context, not part of the bug-001 change.)
**Remediation:** Store password hashes (e.g. bcrypt/argon2) and verify with a constant-time comparison function.

---

### MEDIUM: Typo in password property silently breaks credential check

**File:** `src/routes/auth.js:25`
**Description:** The comparison reads `user.pasword` (missing an `s`), so it evaluates against `undefined`; depending on stored data this can cause the check to never match correctly or to behave unpredictably, undermining the authentication guard. (Adjacent context, not part of the bug-001 change.)
**Remediation:** Correct the property name to `user.password` and back it with hashed verification.

---

### LOW: Missing input format and length validation on registration fields

**File:** `src/routes/auth.js:9-15`
**Description:** The register handler only checks that `username`, `email`, and `password` are truthy; there is no validation of email format, password strength, or field length, allowing malformed or oversized input into the store.
**Remediation:** Validate email syntax and enforce length/strength constraints (e.g. via a schema validator) before calling `addUser`.

---

### INFO: No rate limiting on authentication endpoints

**File:** `src/routes/auth.js:8-30`
**Description:** Neither `/register` nor `/login` applies rate limiting, leaving them open to enumeration and brute-force attempts; the new 409 response also enables email-existence enumeration on registration.
**Remediation:** Add rate limiting and consider a uniform response to reduce account enumeration.

---

## References

- context/bugs/001/fix-summary.md
- /home/alex/Sites/study/homework/homework-4/src/routes/auth.js
