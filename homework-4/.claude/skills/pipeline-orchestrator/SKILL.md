---
name: pipeline-orchestrator
description: Bug fixing pipeline to be used over provided bugs descriptions in `context/bugs` when fix, research, implementation or a full fix is requested.
argument: bugdir
---

## Argument

The skill receives one argument: the path to the bug directory to process (e.g. `context/bugs/001`). All file paths below are relative to that directory unless stated otherwise.

## Execution mode

By default, run all six steps in order from Step 1 through Step 6. If the request names a specific step or a partial range (e.g. "only research", "up to the plan", "steps 1–3"), stop after the last requested step and do not proceed further — even if earlier steps produced clean output.

Each step depends on the output of the previous one. **Always execute steps in order, never skip or reorder them.** A step must fully complete and pass its stop-condition check before the next step begins.

## Steps (run sequentially, 1 → 6)

### Step 1 — Codebase research (inline)

Print: `[Step 1] Codebase research — starting`

**Skip condition:** If `research/codebase-research.md` already exists, print `[Step 1 skipped] research/codebase-research.md already exists` and proceed directly to Step 2.

**Reads:**
- `bug-context.md`

**Writes:** `research/codebase-research.md`

**How:** Read `bug-context.md` to understand the defect. Investigate the issue and found relevant code in `src/`. Record every relevant finding as a `file:line` reference with a verbatim code excerpt. Capture the root cause, the failure mechanism, and the full scope of impact. Use `templates/codebase-research.template.md` as the artifact shape — do not free-form the output.

After writing `research/codebase-research.md`, print:
```
[Step 1 complete] Research written to research/codebase-research.md
```

---

### Step 2 — Research verification

Print: `[Step 2] Research verification — starting`

**Skip condition:** If `research/verified-research.md` already exists, print `[Step 2 skipped] research/verified-research.md already exists` and proceed directly to Step 3.

**Performed by:** subagent `research-verifier`.

**Reads:** `research/codebase-research.md`, referenced files

**Writes:** `research/verified-research.md`

**How:** Invoke the `research-verifier` subagent with `$bugdir` as input and wait for it to finish. It will write `research/verified-research.md`.

**Stop condition:** After the agent finishes, read `research/verified-research.md`.
- Stop if the file is missing.
- Stop if it contains `Research Quality Assessment: LOW`.

On stop: print the reason and the path `research/verified-research.md`, then exit non-zero.

If not stopped, print:
```
[Step 2 complete] Verified research written to research/verified-research.md
```

---

### Step 3 — Implementation planning (inline)

Print: `[Step 3] Implementation planning — starting`

**Skip condition:** If `implementation-plan.md` already exists, print `[Step 3 skipped] implementation-plan.md already exists` and proceed directly to Step 4.

**Reads:** `bug-context.md`, `research/codebase-research.md`

**Writes:** `implementation-plan.md`

**How:** Re-read the codebase research. For each location that needs to change, record the exact `file:line`, quote the current snippet to anchor the location, and write a one-sentence fix intent describing what logic to add, remove, or change and why it resolves the root cause. Do not write replacement code — that is the bug-fixer's job. Use `templates/implementation-plan.template.md` as the artifact shape — do not free-form the output. Fill only the sections defined in the template; do not add any extra sections.

After writing `implementation-plan.md`, print:
```
[Step 3 complete] Implementation plan written to implementation-plan.md
```

---

### Step 4 — Bug fix

Print: `[Step 4] Bug fix — starting`

**Skip condition:** If `fix-summary.md` already exists, print `[Step 4 skipped] fix-summary.md already exists` and proceed directly to Step 5.

**Performed by:** subagent `bug-fixer`.

**Reads:** `implementation-plan.md`

**Writes:** `fix-summary.md`, edits to `src/`

**How:** Invoke the `bug-fixer` subagent with `$bugdir` as input and wait for it to finish. It will apply the plan and write `fix-summary.md`.

**Stop condition:** After the agent finishes, read `fix-summary.md`.
- Stop if the file is missing.
- Stop if it does not contain `Overall Status: PASSED` (i.e. status is FAILED or absent).

On stop: print the reason and the path `fix-summary.md`, then exit non-zero.

If not stopped, print:
```
[Step 4 complete] Fix summary written to fix-summary.md
```

---

### Step 5 — Security review

Print: `[Step 5] Security review — starting`

**Performed by:** subagent `security-verifier`.

**Reads:** `fix-summary.md`, changed source files listed in it

**Writes:** `security-report.md`

**How:** Invoke the `security-verifier` subagent with `$bugdir` as input and wait for it to finish. It will write `security-report.md`.

**Stop condition:** After the agent finishes, read `security-report.md`.
- Stop if the file is missing.
- Stop if it contains any finding labelled `CRITICAL`.

On stop: print the reason and the path `security-report.md`, then exit non-zero.

If not stopped, print:
```
[Step 5 complete] Security report written to security-report.md
```

---

### Step 6 — Unit test generation

Print: `[Step 6] Unit test generation — starting`

**Performed by:** subagent `unit-test-generator`.

**Reads:** `research/codebase-research.md`, `fix-summary.md`, changed source files listed in it

**Writes:** `test-report.md`, new test files under `tests/`

**How:** Invoke the `unit-test-generator` subagent with `$bugdir` as input and wait for it to finish. It will write `test-report.md` and any new test files.

**Stop condition:** After the agent finishes, read `test-report.md`.
- Stop if the file is missing.
- Stop if it records any failing test.

On stop: print the reason and the path `test-report.md`, then exit non-zero.

**Completion:** If all six steps finished without stopping, print:
```
[Pipeline complete] All steps passed for <bug-dir>
```

## Stop-condition summary

| After step | Stop if |
|------------|---------|
| 2 — research-verifier | `research/verified-research.md` missing, or contains `Research Quality Assessment: LOW` |
| 4 — bug-fixer | `fix-summary.md` missing, or does not contain `Overall Status: PASSED` |
| 5 — security-verifier | `security-report.md` missing, or contains any `CRITICAL` finding |
| 6 — unit-test-generator | `test-report.md` missing, or records any failing test |
