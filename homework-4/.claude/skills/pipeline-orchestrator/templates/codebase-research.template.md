# Codebase Research — {{ bug-id }}

## Bug Summary

{{ One paragraph from bug-context.md: what is the reported defect and what behaviour is expected. }}

## Relevant Files

| File | Purpose |
|------|---------|
| {{ path/to/file.js }} | {{ Why this file is relevant to the bug }} |

## Root Cause

{{ Identify the exact function, variable, or code path responsible for the defect. Explain the mechanism of failure — what condition triggers it and why the current code produces the wrong result. }}

## Evidence

<!-- One entry per relevant code location. Quote the exact lines from the file. -->

### {{ path/to/file.js:LINE }}

```js
{{ verbatim code excerpt at the cited line }}
```

{{ One sentence explaining what this excerpt shows and why it is relevant. }}

## Scope of Impact

{{ Which callers, routes, or flows are affected. Are there edge cases beyond the primary failure scenario? }}

## References

<!-- Deduplicated list of every file read during research -->

- {{ path/to/file.js }}
