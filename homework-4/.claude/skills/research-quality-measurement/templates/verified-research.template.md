# Verified Research — {{ bug-id }}

## Verification Summary

{{ 
One short paragraph. The overall quality verdict (PASS / FAIL). Then state how many file:line references were checked, how many matched, whether any fabricated snippets were found. Example: 
```Overal: PASS. 
11 of 12 references verified (91%). No fabricated snippets found. Root-cause description is complete.
``` 
}}

## Verified Claims

| Claim | file:line | Verified? | Notes |
|-------|-----------|-----------|-------|
| {{ Description of claim from research }} | {{ path/to/file.js:42 }} | {{ Yes / No }} | {{ matched exactly / line offset by N / not found }} |

<!-- Add one row per claim from codebase research -->

## Discrepancies Found

<!-- List every claim that could not be verified. If none, write "None." -->

**{{ file:line cited in research }}** 
_Research stated_: `{{ quoted snippet }}` 
_Actual source at location_: `{{ what is actually there }}`.

## Research Quality Assessment

Research Quality Assessment: {{ HIGH | MEDIUM | LOW }}

{{ One sentence citing the specific criterion from the skill that determined this level. Example: "92% of references verified, no fabricated snippets, and root-cause description is complete — meets all HIGH criteria." }}

## References

<!-- Deduplicated list of every source file opened during verification -->

- {{ path/to/file.js }}
