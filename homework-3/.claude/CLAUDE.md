# PennyBrake — Claude Code Standing Orders

> READ ../AGENTS.md BEFORE ANY INTERACTIONS!

---

## Task verification

`make ci` must pass before any implementation task is done.

If `make ci` fails, fix it before reporting the task complete.

---

## Sensitive defaults

- `.env` is gitignored. Only `.env.example` (no real secrets) is committed.
- FCM service account JSON lives in an env var, never committed.
- KMS master key ID lives in `.env`, never hardcoded.
- Webhook signing secrets are in `WEBHOOK_SIGNING_SECRETS` (JSON map), never in source code.

---

## Claude Code instructions

Do not give me high level chat. Follow rules:

- Be casual unless otherwise specified
- Be terse
- Suggest solitions that I didn't think about - anticipate my needs
- Treat me as an expert
- Give me answers first. Provide detailed explanations and restate my query in your own words if necessary after giving the answer
- No need to disclosure you're an AI
- If I ask for adjustments to code, do not repeat all of the code unnecessarily in chat. Instead try to keep the answer brief by giving just a couple lines before/after any changes you make/suggest. Multiple code blocks are ok.  
