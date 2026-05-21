# Bug 003 — Security: Forgeable Auth Token

## Location
- **File:** `src/middleware/auth.js`
- **Functions:** `generateToken`, `authMiddleware`

## Problem
The auth token is simply `base64(email)` with no signature, no secret, and no expiry:

```js
function generateToken(user) {
  return Buffer.from(user.email).toString('base64');
}
```

`authMiddleware` base64-decodes the token to extract an email and looks up the user — no verification that the token was issued by the server.

## Expected Behavior
```
GET /api/profile
Authorization: Bearer <forged-token>
→ HTTP 401  { "error": "Unauthorized" }
```

## Actual Behavior
```
GET /api/profile
Authorization: Bearer $(echo -n 'victim@example.com' | base64)
→ HTTP 200  { "id": 1, "username": "...", "email": "victim@example.com" }
```

## Steps to Reproduce
1. Register any user via `POST /api/register`
2. Without logging in, craft a token: `echo -n '<email>' | base64`
3. Call `GET /api/profile` with `Authorization: Bearer <forged-token>`
4. Receives 200 with full user profile
