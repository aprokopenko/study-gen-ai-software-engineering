# Security Report — 003

## Summary

Reviewed the single changed file `src/middleware/auth.js` (HMAC token generation and verification added by the fix). Four findings were identified, the highest being CRITICAL: the signature verification logic invokes `crypto.timingSafeEqual` but discards its boolean result, so any token with a syntactically valid but incorrect signature is accepted, producing a complete authentication bypass. Additional findings concern a hardcoded fallback secret, a length-mismatch information leak, and an unverified base64 payload.

## Findings

### CRITICAL: Signature verification result is ignored (auth bypass)

**File:** `src/middleware/auth.js:37`
**Description:** `crypto.timingSafeEqual(...)` returns a boolean and only throws when the two buffers differ in length; its return value is never checked, so a forged token with the correct length but an invalid signature passes verification, allowing any attacker who knows or guesses a valid email to authenticate as that user.
**Remediation:** Capture the return value (`if (!crypto.timingSafeEqual(...)) return 401;`) and reject when it is false, while still handling the length-mismatch throw.

---

### HIGH: Hardcoded fallback secret

**File:** `src/middleware/auth.js:4`
**Description:** When `API_SECRET` is unset the code falls back to the literal `'super-secret-key-123'`, a publicly known value in source control that lets an attacker forge valid HMAC signatures for any email and bypass authentication.
**Remediation:** Require `API_SECRET` to be present and fail fast (throw at startup) if it is missing instead of using a hardcoded default.

---

### MEDIUM: Length-mismatch path returns same response but timing differs / DoS surface

**File:** `src/middleware/auth.js:38`
**Description:** `timingSafeEqual` throws when `signature` and `expectedSignature` lengths differ; the catch block returns 401 correctly, but an attacker can use the throw-vs-no-throw behavior plus the ignored-return bug to distinguish valid-length signatures, and the comparison is only timing-safe within equal-length inputs.
**Remediation:** Normalize/validate signature length (e.g. compare hashed digests of fixed length) before `timingSafeEqual` and ensure the comparison result is always enforced.

---

### LOW: Base64 payload decoded without validation

**File:** `src/middleware/auth.js:45`
**Description:** `Buffer.from(payload, 'base64').toString('utf8')` is lenient and never throws on malformed base64, so the `try/catch` provides no real validation; the decoded email is passed directly to `findByEmail` relying solely on the store lookup to reject bad input.
**Remediation:** Validate the decoded value matches an expected email format before using it, and rely on the verified signature (once fixed) as the authoritative integrity check.

---

## References

- context/bugs/003/fix-summary.md
- src/middleware/auth.js
