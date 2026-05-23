---
name: research-quality-measurement
description: Defines research quality levels, the evaluation procedure to grade codebase research, and the evaluation report format.
---

## Codebase research measurements qualuty levels

### HIGH

All of the following conditions are met:

- **≥ 90% of `file:line` references verified**: Every cited location was opened and the quoted snippet matches the actual source code exactly.
- **No fabricated snippets**: Zero instances of code in the research that does not exist at the cited location.
- **Complete root-cause description**: The research names the exact function, variable, or code path responsible for the bug, explains the mechanism of failure, and identifies the full scope of impact.

### MEDIUM

One or more of the following is true, but none of the LOW conditions apply:

- **70–89% of `file:line` references verified**: A minority of cited locations have minor drift (e.g. line number off by a few due to edits) but the code snippet is still recognisably present.
- **No fabricated snippets**: All cited code exists in the file, even if at slightly different lines.
- **Partial root-cause description**: The research identifies the correct general area and failure mode, but is missing secondary callers, edge-case conditions, or the full propagation path.

### LOW

One or more of the following is true:

- **< 70% of `file:line` references verified**: More than 30% of cited locations either do not exist or do not contain the quoted code.
- **One or more fabricated snippets**: At least one code excerpt cited in the research cannot be found anywhere in the referenced file.
- **Absent or vague root-cause description**: The research fails to identify the specific code responsible for the bug, or the explanation is too generic to form a concrete implementation plan from.

## Evaluation procedure

Follow these steps in order for provided codebase research file being verified:

1. **Collect all claims** — Parse every `file:line` reference in `codebase-research.md` into a list.
2. **Open and check each citation** — For each `file:line`, read the file at that line. Mark the claim **verified** if the snippet in the research matches the actual source; mark it **refuted** otherwise.
3. **Tally the verified percentage** — `verified_count / total_count * 100`.
4. **Scan for fabricated snippets** — Identify any code block in the research that cannot be found in the referenced file at all.
5. **Assess root-cause completeness** — Determine whether the research names the exact code responsible and explains the failure mechanism.
6. **Map to a quality level** — Apply the HIGH / MEDIUM / LOW criteria above using the tally and findings from steps 3–5. Use the lowest level for which any condition is triggered.
7. **Prepare the report** — Follow the report format defined.

## Report format

The canonical shape of a verification report is defined in `templates/verified-research.template.md`. Consumers of this skill must use that template rather than free-forming the report. The template ships five `##` sections with `{{ ... }}` placeholders:

1. **Verification Summary** — overall PASS/FAIL and the chosen quality level.
2. **Verified Claims** — per-claim table with columns: claim, `file:line`, verified?
3. **Discrepancies Found** — claims that failed, with what was cited vs. what the source actually contains.
4. **Research Quality Assessment** — the level (HIGH / MEDIUM / LOW) and one sentence of reasoning citing the criteria above.
5. **References** — deduplicated list of every source file consulted.

Fill every placeholder; do not add, remove, or rename sections.
