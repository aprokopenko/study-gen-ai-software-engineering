# 4-Agent Bug-Fix Pipeline Specification

> Ingest the information from this file, implement the Low-Level Tasks, and generate the code that will satisfy the High and Mid-Level Objectives.

## High-Level Objective
- Build a single-command, file-driven pipeline that for each seeded bug in `context/bugs/` runs research → verification → planning → fix → security review → unit-test generation, persisting every step's artifact alongside the bug so the run is fully auditable.

## Mid-Level Objectives
1. A single entry script (`run-pipeline.sh`) executes the whole pipeline end-to-end with no manual per-agent invocation between steps.
2. The pipeline iterates over every bug folder under `context/bugs/` and processes only those that have not yet been completed, so the same command can be safely re-run after partial runs.
3. Each bug is treated as an isolated work unit: every artifact (research, plan, fix, security review, tests) lives next to its `bug-context.md` under `context/bugs/XXX/`.
4. Codebase research is produced first by reading the source and the bug context, capturing the relevant file:line references and code excerpts the fix will rely on.
5. A separate verification step independently re-checks every claim in the research and labels overall research quality, so downstream steps never plan on hallucinated references.
6. The implementation plan is written only after the research has passed verification, so planning never proceeds on research that failed its quality check.
7. A dedicated Bug Fixer agent applies the plan, runs the project's test command after the change, and records before/after snippets and the test outcome in a fix summary.
8. A dedicated Security Verifier agent reviews only the changed files after the fix, classifies findings by severity (CRITICAL/HIGH/MEDIUM/LOW/INFO), and never edits source.
9. A dedicated Unit Test Generator agent writes new tests strictly for the code that changed during the fix, runs them, and records the results.
10. Two reusable knowledge skills are shipped with the pipeline: one defining research-quality levels used by the verifier, one defining the FIRST principles used by the test generator.
11. Each of the four required agents declares an explicit model choice in its frontmatter, matched to the cost/capability needs of its task (heavier reasoning for verification and security; faster/cheaper for routine fix and scaffold work).
12. Completion of a bug is detected solely by the presence of `test-report.md` in its folder, so the pipeline's idempotency rule is observable from the filesystem alone.
13. Agents communicate only through files on disk — no shared memory or in-process handoff — so any intermediate state can be inspected, replayed, or reviewed by a human.
14. The pipeline run is observable from the terminal: each bug, each step, and each agent invocation prints a clear status line so a reviewer can follow progress live.
15. The orchestrator does not blindly chain steps — it reads each sub-agent's output report (verified research, fix summary, security report, test report) and may abort the pipeline early when a report signals a blocking condition (e.g. LOW research quality, failing tests after a fix, CRITICAL security finding).
16. The pipeline operates on the existing User Registration micro-service (`src/`, `tests/`) and uses the project's Docker-based `make test` to validate fixes — no host Node.js required.
17. After a successful run, every processed bug folder contains the full chain of artifacts (`codebase-research.md`, `verified-research.md`, `implementation-plan.md`, `fix-summary.md`, `security-report.md`, `test-report.md`) plus any new test files in `tests/`.

## Implementation Notes
- Skills follow the directory-per-skill layout: each skill lives at `.claude/skills/<skill-name>/SKILL.md` (e.g. `.claude/skills/pipeline-orchestrator/SKILL.md`). The skill name is the folder name; the body file is always `SKILL.md`. A skill may ship supporting files next to `SKILL.md` (e.g. a `templates/` folder), as `research-quality-measurement` does for its report-format template.
- Orchestrator is a **skill** (`.claude/skills/pipeline-orchestrator/SKILL.md`), not an agent. It loads into a plain `claude` invocation and directs the session through the six steps for a single bug.
- Research and Planning are **inline** steps performed by the orchestrator's own Claude instance — no separate agent files. The four `.claude/agents/*.md` files are sub-agents the orchestrator invokes by name.
- Step order per bug: (1) inline research → (2) `research-verifier` agent → (3) inline planning → (4) `bug-fixer` agent → (5) `security-verifier` agent → (6) `unit-test-generator` agent.
- The inline plan (`implementation-plan.md`) records, per change location, the exact `file:line`, a snippet quoted to anchor the location, and a one-sentence fix intent — it does **not** contain replacement code. Writing the actual code is the `bug-fixer`'s job.
- `run-pipeline.sh` is the only entry point. It iterates `context/bugs/*/` in lexical order and skips any directory that already contains `test-report.md`.
- Per-bug invocation pattern: `claude --skill .claude/skills/pipeline-orchestrator/SKILL.md "<bug-dir>"`. The skill receives the bug directory path as its argument.
- The orchestrator hands control to a sub-agent by name (the agent filename without `.md`) and waits for it to finish; the sub-agent's report file on disk is the handoff signal.
- After each sub-agent finishes, the orchestrator reads its report file and decides whether to continue: stop on missing report, on `Research Quality Assessment: LOW`, on `Overall Status: FAILED` in the fix summary, on any CRITICAL finding in the security report, or on failing tests in the test report. The reason for stopping is printed to the terminal.
- Suggested model assignments (recorded in each agent's frontmatter):
  - `research-verifier` — `claude-opus-4-7` (careful fact-checking)
  - `bug-fixer` — `claude-haiku-4-5` (routine plan execution, cheap/fast)
  - `security-verifier` — `claude-opus-4-7` (security reasoning)
  - `unit-test-generator` — `claude-sonnet-4-6` (test scaffolding)
- Test execution inside agents must use `make test` (which runs `docker compose run --rm app npm test`) — never invoke `npm` on the host. This matches the project-wide rule that Node.js runs only inside Docker.
- File handoff contract between steps:
  | Step | Reads | Writes |
  |---|---|---|
  | Research (inline) | `src/`, `bug-context.md` | `research/codebase-research.md` |
  | Verify research | `codebase-research.md`, `src/` | `research/verified-research.md` |
  | Plan (inline) | `bug-context.md`, `codebase-research.md` | `implementation-plan.md` |
  | Fix | `implementation-plan.md` | `fix-summary.md` + code edits |
  | Security review | `fix-summary.md` + changed files | `security-report.md` |
  | Generate tests | `fix-summary.md` + changed files | `test-report.md` + new test files |
- The Research Verifier must use `.claude/skills/research-quality-measurement/SKILL.md` as reference knowledge: follow its evaluation procedure, emit a quality level (e.g. HIGH / MEDIUM / LOW) per that skill's vocabulary, and write `verified-research.md` in the report format the skill defines (its `templates/verified-research.template.md`). The skill supplies the knowledge; the agent does the writing.
- The Unit Test Generator must use `.claude/skills/unit-tests-FIRST/SKILL.md` as reference knowledge: confirm each generated test against the skill's FIRST checklist (Fast, Independent, Repeatable, Self-validating, Timely) and record the filled-in checklist results in `test-report.md`. The skill supplies the principles and checklist; the agent does the writing.
- The Security Verifier writes a report only — it must not edit source. Findings include severity, `file:line`, and a remediation suggestion.
- The pipeline targets the mini-app already documented in [specs/mini-app.md](mini-app.md); it must not reshape that app's stack or interfaces.

## Context

### Beginning context
- Mini-app already exists in `src/` and `tests/` per [specs/mini-app.md](mini-app.md)
- Seeded bug folders exist: `context/bugs/001/bug-context.md`, `002/bug-context.md`, `003/bug-context.md`
- `make test` passes against the mini-app in its "buggy" state (covers happy-path only)
- No `.claude/agents/`, `.claude/skills/`, or `run-pipeline.sh` yet
- No `research/`, `implementation-plan.md`, `fix-summary.md`, `security-report.md`, or `test-report.md` in any bug folder

### Ending context
- `.claude/skills/pipeline-orchestrator/SKILL.md` — Orchestrator skill that drives the per-bug flow and gates each step on the previous report
- `.claude/skills/research-quality-measurement/SKILL.md` — Reusable knowledge: research-quality levels, evaluation procedure, and report format
- `.claude/skills/research-quality-measurement/templates/verified-research.template.md` — Report-format template a consumer fills in to produce `verified-research.md`
- `.claude/skills/unit-tests-FIRST/SKILL.md` — Reusable knowledge: FIRST principles + a verification checklist used by the test generator
- `.claude/agents/research-verifier.md` — Verifies `codebase-research.md`
- `.claude/agents/bug-fixer.md` — Applies `implementation-plan.md` and runs `make test`
- `.claude/agents/security-verifier.md` — Reviews changed code, writes `security-report.md`
- `.claude/agents/unit-test-generator.md` — Generates tests for changed code, runs them
- `run-pipeline.sh` — Single-command entry point that iterates unprocessed bug dirs
- For each bug (001, 002, 003):
  - `context/bugs/XXX/research/codebase-research.md`
  - `context/bugs/XXX/research/verified-research.md`
  - `context/bugs/XXX/implementation-plan.md`
  - `context/bugs/XXX/fix-summary.md`
  - `context/bugs/XXX/security-report.md`
  - `context/bugs/XXX/test-report.md`
- `tests/` contains new test files generated by the test generator (e.g. `tests/auth.duplicate-email.test.js`, etc.)
- `src/` is patched so all three seeded defects are resolved

## Low-Level Tasks

### 1. Create directory skeleton for pipeline assets

Create the empty folders the pipeline writes into, so subsequent tasks can drop files in without `mkdir -p` race conditions.

**Files:** `.claude/agents/.gitkeep`, `.claude/skills/.gitkeep`

**Details:**
- Create `.claude/agents/` and `.claude/skills/` directories under the project's `.claude/` folder.
- Add a `.gitkeep` in each to keep them in git until real files arrive.

**Verify:**
- `test -d .claude/agents && test -d .claude/skills && echo ok` prints "ok"

---

### 2. Create the research-quality-measurement skill

This is a **reusable knowledge skill** — it carries no pipeline-specific state and could be picked up by any agent or person grading research. It supplies three things: (1) the research quality levels, (2) the evaluation procedure to grade `codebase-research.md`, and (3) the report format, shipped as a separate template file. The skill does **not** generate the `verified-research.md` artifact itself — a consumer (here, the `research-verifier` agent) applies this knowledge and writes the artifact.

**Files:**
- `.claude/skills/research-quality-measurement/SKILL.md`
- `.claude/skills/research-quality-measurement/templates/verified-research.template.md`

**Details:**
- `SKILL.md` frontmatter with `name`, `description`. The `description` reflects the skill's three jobs (defines quality levels, the evaluation procedure, and the report format).
- `SKILL.md` body defines exactly three quality levels: **HIGH**, **MEDIUM**, **LOW**. For each level, document the criteria: percentage of file:line references that verified, presence/absence of fabricated snippets, completeness of root-cause description.
- `SKILL.md` includes an **"Evaluation procedure"** section: the ordered steps for grading research — open each cited file at the cited line, mark each claim verified/refuted, tally the verified percentage, then map that tally to a level using the criteria above.
- `SKILL.md` includes a **report-format** section that points to `templates/verified-research.template.md` as the canonical shape of a verification report — the consumer fills the template rather than free-forming the report. This section describes the format; it does not perform the write.
- `templates/verified-research.template.md` is a fill-in skeleton showcasing the report structure, with all five required sections as `##` headings and placeholder (`{{ ... }}`) guidance under each: **Verification Summary** (overall PASS/FAIL + the chosen quality level), **Verified Claims** (per-claim table: claim, `file:line`, verified?), **Discrepancies Found**, **Research Quality Assessment** (level + reasoning that cites the criteria), **References**.

**Verify:**
- `SKILL.md` exists and contains the strings "HIGH", "MEDIUM", "LOW"
- `SKILL.md` contains an "Evaluation procedure" section and references `templates/verified-research.template.md` as the report format
- `templates/verified-research.template.md` exists and contains all five section headings (`grep -c "^## " ...` is at least 5)

---

### 3. Create the unit-tests-FIRST skill

This is a **reusable knowledge skill** — it carries no pipeline-specific state and could be picked up by anyone writing tests. It supplies two things: (1) the FIRST principles for writing good unit tests, and (2) a checklist to confirm a given test satisfies FIRST. The skill does **not** produce or write any report — recording results is the consumer's job (here, the `unit-test-generator` agent writes them into its `test-report.md`).

**Files:** `.claude/skills/unit-tests-FIRST/SKILL.md`

**Details:**
- Frontmatter with `name`, `description`. The `description` reflects the skill's two jobs (defines FIRST principles and a verification checklist).
- Body defines: **F**ast, **I**ndependent, **R**epeatable, **S**elf-validating, **T**imely — one section per letter with a one-paragraph explanation and a one-line "applied to this project" hint (e.g. "Independent → use `beforeEach(clearUsers)`").
- Include a **"FIRST checklist"** section: one yes/no check per principle that an author runs against a single test to confirm it satisfies FIRST. This is knowledge the consumer applies — the skill describes the checks, it does not fill them in or emit a report.

**Verify:**
- File exists; `grep -E "Fast|Independent|Repeatable|Self-validating|Timely" .claude/skills/unit-tests-FIRST/SKILL.md` shows all five terms
- Contains a section titled "Checklist"
- Body does not write or reference producing a report (reporting belongs to the test-generator agent)

---

### 4. Create the pipeline-orchestrator skill

This skill is loaded into a `claude` session and drives the six steps for a single bug directory passed as argument. It is not an agent — it directs the session inline and invokes the four sub-agents at the right moments, reading each sub-agent's report before deciding to continue.

**Files:** `.claude/skills/pipeline-orchestrator/SKILL.md`

**Details:**
- Frontmatter: `name: pipeline-orchestrator`, `description: ...`.
- Body documents the six ordered steps with, for each: inputs (files to read), outputs (files to write), the agent (if any) that performs it, and the post-step check the orchestrator runs against the produced report.
- Steps 1 and 3 (research, planning) are inline — performed by the orchestrating Claude itself.
- Steps 2, 4, 5, 6 hand control to sub-agents `research-verifier`, `bug-fixer`, `security-verifier`, `unit-test-generator` (invoked by name; the agent runs to completion and writes its report file).
- Document the per-bug-dir argument convention (path passed in, e.g. `context/bugs/001`).
- Document the **stop conditions** the orchestrator must enforce after each step:
  - Step 2 — stop if `verified-research.md` is missing or `Research Quality Assessment` is `LOW`.
  - Step 4 — stop if `fix-summary.md` is missing or `Overall Status` is not `PASSED` / tests failed.
  - Step 5 — stop if `security-report.md` is missing or contains any `CRITICAL` finding (the fix introduced a worse vulnerability than it solved).
  - Step 6 — stop if `test-report.md` is missing or any generated test fails.
- On stop: print the reason and the offending report path, then exit non-zero so `run-pipeline.sh` doesn't mark the bug as completed.

**Verify:**
- File exists with frontmatter and `name: pipeline-orchestrator`
- Body references all four sub-agent names (the orchestrator calls sub-agents only; it does not load the knowledge skills itself — those are used by the agents)
- Body documents each of the four stop conditions above

---

### 5. Create the research-verifier agent

**Files:** `.claude/agents/research-verifier.md`

**Details:**
- Frontmatter: `name`, `description`, `model: claude-opus-4-7`, `tools: Read, Grep, Glob, Write`.
- Role: read `<bug-dir>/research/codebase-research.md`, open each cited file at the cited line, and confirm the snippet matches the actual source.
- Must use `.claude/skills/research-quality-measurement/SKILL.md` as reference knowledge: follow its evaluation procedure to grade the research, then write `<bug-dir>/research/verified-research.md` in the report format the skill defines (its `templates/verified-research.template.md`; sections: Verification Summary, Verified Claims, Discrepancies Found, Research Quality Assessment, References). The skill defines levels/procedure/format only — this agent performs the evaluation and writes the artifact.
- Must not edit source code.

**Verify:**
- File exists with frontmatter `model: claude-opus-4-7`
- Body references `.claude/skills/research-quality-measurement/SKILL.md` and `verified-research.md`

---

### 6. Create the bug-fixer agent

**Files:** `.claude/agents/bug-fixer.md`

**Details:**
- Frontmatter: `name`, `description`, `model: claude-haiku-4-5`, `tools: Read, Edit, Write, Bash`.
- Role: read `<bug-dir>/implementation-plan.md`, implement the fix intent specified in the plan (the plan gives intent and a location anchor, not replacement code — the fixer writes the actual code), run `make test` after each file change, and write `<bug-dir>/fix-summary.md`.
- `fix-summary.md` sections: Changes Made (per file: location, before, after, test result), Overall Status (PASSED / FAILED), Manual Verification, References.
- On test failure: stop, document the failing test output verbatim in fix-summary.md, set `Overall Status: FAILED`, do not continue.

**Verify:**
- File exists with frontmatter `model: claude-haiku-4-5`
- Body references `implementation-plan.md`, `fix-summary.md`, and `make test`
- Body documents both `Overall Status: PASSED` and `Overall Status: FAILED` values (so the orchestrator's stop check has a stable string to match)

---

### 7. Create the security-verifier agent

**Files:** `.claude/agents/security-verifier.md`

**Details:**
- Frontmatter: `name`, `description`, `model: claude-opus-4-7`, `tools: Read, Grep, Glob, Write`.
- Role: read `<bug-dir>/fix-summary.md` and the files it lists as changed; scan for injection, hardcoded secrets, insecure comparisons, missing validation, unsafe deps, and XSS/CSRF where relevant.
- Output `<bug-dir>/security-report.md` only; never edit source files.
- Each finding must include severity (CRITICAL/HIGH/MEDIUM/LOW/INFO), `file:line`, and a remediation suggestion.

**Verify:**
- File exists with frontmatter `model: claude-opus-4-7`
- Body references all five severity labels and `security-report.md`
- `tools:` line does NOT contain `Edit` or `Write` against source paths (write tool present only to produce the report)

---

### 8. Create the unit-test-generator agent

**Files:** `.claude/agents/unit-test-generator.md`

**Details:**
- Frontmatter: `name`, `description`, `model: claude-sonnet-4-6`, `tools: Read, Write, Bash`.
- Role: read `<bug-dir>/fix-summary.md` and the changed source files; generate Jest + supertest tests **only for the changed code**; run `make test`; write `<bug-dir>/test-report.md`.
- Must use `.claude/skills/unit-tests-FIRST/SKILL.md` as reference knowledge: apply its FIRST checklist to each generated test and record the filled-in checklist results per test in `test-report.md`. The skill supplies the principles and checklist; this agent does the writing.
- New tests go under `tests/` with descriptive filenames (e.g. `tests/auth.duplicate-email.test.js`).

**Verify:**
- File exists with frontmatter `model: claude-sonnet-4-6`
- Body references `.claude/skills/unit-tests-FIRST/SKILL.md`, `test-report.md`, and `make test`

---

### 9. Create run-pipeline.sh

The single-command entry point. Iterates `context/bugs/*/` in lexical order, skips dirs that already contain `test-report.md`, and invokes the orchestrator skill once per remaining dir.

**Files:** `run-pipeline.sh`

**Details:**
```bash
#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
BUGS_DIR="$ROOT/context/bugs"

processed=0
skipped=0

for bug_dir in "$BUGS_DIR"/*/; do
  bug_id="$(basename "$bug_dir")"
  if [[ -f "$bug_dir/test-report.md" ]]; then
    echo "[skip] $bug_id — already processed (test-report.md present)"
    skipped=$((skipped+1))
    continue
  fi
  echo "[run]  $bug_id — invoking orchestrator"
  claude --skill "$ROOT/.claude/skills/pipeline-orchestrator/SKILL.md" "$bug_dir"
  processed=$((processed+1))
done

echo "Done. processed=$processed skipped=$skipped"
```
- Must be executable (`chmod +x run-pipeline.sh`).

**Verify:**
- `bash -n run-pipeline.sh` exits 0 (syntax ok)
- `test -x run-pipeline.sh` (executable bit set)
- Dry run with a fake bug dir containing `test-report.md` prints `[skip]` and increments the skipped counter

---

### 10. Smoke-test the pipeline against bug 001

Run the full pipeline against the first bug folder and confirm all expected artifacts land on disk.

**Files:** none (verification-only task)

**Verify:**
- `./run-pipeline.sh` runs to completion without error for `context/bugs/001/`
- All six artifacts exist:
  - `context/bugs/001/research/codebase-research.md`
  - `context/bugs/001/research/verified-research.md`
  - `context/bugs/001/implementation-plan.md`
  - `context/bugs/001/fix-summary.md`
  - `context/bugs/001/security-report.md`
  - `context/bugs/001/test-report.md`
- `make test` passes after the run
- `git diff src/routes/auth.js` shows the duplicate-email check now exists
- Re-running `./run-pipeline.sh` prints `[skip] 001` for the already-processed bug

---

### 11. Run the full pipeline across all seeded bugs

Process the remaining bugs (002 and 003) and confirm all three are resolved end-to-end.

**Files:** none (verification-only task)

**Verify:**
- `./run-pipeline.sh` completes without error
- All three bug folders each contain the six artifacts listed in Task 10
- `make test` passes with the new generated test files included
- Integration checks from [specs/mini-app.md](mini-app.md) Task 9 now show the **fixed** behaviour:
  - Duplicate registration returns 409
  - Login with correct password returns 200 with a token
  - A forged `base64(email)` token no longer grants access to `/api/profile`
