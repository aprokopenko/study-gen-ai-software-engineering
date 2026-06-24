# Screenshots

### 01 — App planning: choosing the mini-app with Kiro
![App planning — Kiro recommends User Registration API](01-app-planning-01.png)

### 02 — App planning: refining task order (infra before app code)
![App planning — task reordering for real verifiability](01-app-planning-02.png)

### 03 — App planning: exploring mini-app options (Calculator vs Registration)
![App planning — option comparison](01-app-planning-03.png)

### 04 — App planning: writing the spec with Docker + Makefile requirements
![App planning — spec authoring](01-app-planning-04.png)

### 05 — App implementation: Kiro creates 16 files in parallel
![App implementation — 16 files created in one batch](02-app-implementation-01.png)

### 06 — App implementation: seeded bugs confirmed via live curl
![Bugs confirmed — duplicate 201, login 401, forged token works](02-app-implementation-02.png)

### 07 — Pipeline architecture: understanding skill vs agent in Claude
![Pipeline architecture exploration](03-understanding-pipeline-01.png)

### 08 — Pipeline architecture: how run-pipeline.sh chains agents
![Pipeline runner design](03-understanding-pipeline-02.png)

### 09 — Pipeline architecture: correcting orchestrator to skill + inline steps
![Pipeline orchestrator as skill, not agent](03-understanding-pipeline-03.png)

### 10 — Pipeline spec: drafting pipeline.md in Claude (opus)
![Pipeline spec draft](04-pipeline-spec-draft-01.png)

### 11 — Pipeline spec: manual corrections (skill path format, model choices)
![Pipeline spec manual corrections](04-pipeline-spec-draft-02.png)

### 12 — Pipeline spec: adding orchestrator stop-condition logic
![Pipeline spec stop conditions added](04-pipeline-spec-draft-03.png)

### 13 — Research-quality-measurement skill created with HIGH/MEDIUM/LOW levels
![Research quality skill](05-research-skill-01.png)

### 14 — Orchestrator skill: 6-step flow implemented, templates created
![Orchestrator skill implementation](06-pipeline-runner-01.png)

### 15 — Non-interactive permissions: --dangerously-skip-permissions vs settings.json
![Permissions challenge](06-pipeline-runner-02.png)

### 16 — run-pipeline.sh: MAX_BUGS debug guard, opus model flag added
![run-pipeline.sh finalized](06-pipeline-runner-03.png)

### 17 — Research verifier agent created and verified against spec
![Research verifier agent](07-research-verifier-01.png)

### 18 — Bug fixer agent: retry logic and single test run refined
![Bug fixer agent](08-bug-fixer-01.png)

### 19 — Security verifier agent: all severity labels, no Edit tool
![Security verifier agent](09-security-verifier-01.png)

### 20 — Unit test generator agent: FIRST skill + never-edit-src constraint
![Unit test generator agent](10-unit-test-agent-01.png)

### 21 — Pipeline execution: bugs 001 and 002 pass all 6 steps
![Pipeline exec — bugs 001 and 002 complete](11-pipeline-exec-01.png)

### 22 — Pipeline execution: bug 003 correctly stopped at Step 5 (CRITICAL finding)
![Pipeline exec — bug 003 stopped on CRITICAL security finding](11-pipeline-exec-02.png)
