
## 1. Create Document Metadata Skill

Create `skills/document-metadata.md`:

```
description: Manages author attribution and version control in markdown document frontmatter
argument-hint: Optional: version type (major, minor, patch) or 'update' for timestamp only
---

# Document Metadata Manager

Automatically manage author attribution and versioning in markdown document frontmatter.

## Author Attribution

When creating or modifying markdown files, add/update author metadata:

- Extract author name from git config (`git config user.name`)
- Add `created` timestamp (ISO 8601 format) on new documents
- Update `last-modified` timestamp on edits
- Preserve existing frontmatter fields

## Version Management

Manage semantic versioning (major.minor.patch):

1. Read current version from frontmatter (defaults to `0.1.0` if missing)
2. Increment based on argument:
   - `major`: 1.2.3 → 2.0.0
   - `minor`: 1.2.3 → 1.3.0
   - `patch`: 1.2.3 → 1.2.4
   - `update`: no version change, only update timestamps
3. Add `version-updated` timestamp

## Example Frontmatter

```yaml
---
author: John Doe
version: 1.2.0
created: 2026-03-12T10:00:00Z
last-modified: 2026-03-12T14:30:00Z
version-updated: 2026-03-12T14:30:00Z
---
```
```
