# Implementation Plan — {{ bug-id }}

## Objective

{{ One sentence: what this plan achieves, derived from codebase-research.md. }}

## Changes

<!-- One block per location to be modified. -->

### {{ path/to/file.js }}

**Location:** line {{ N }} — function/block `{{ name }}`

**Before:**

```js
{{ exact current snippet that anchors the location }}
```

**Fix intent:** 

{{ what condition, logic, or value to add/remove/change and why it fixes the root cause. Do not write replacement code. }}

---

<!-- Repeat the block above for each additional change location. -->

## Verification

After applying all changes, run `make test`. Record the full output in `fix-summary.md`.

## References

- {{ bug-dir }}/research/codebase-research.md`
- {{ each source file to be changed }}
