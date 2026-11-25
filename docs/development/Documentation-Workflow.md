---
title: Documentation maintenance workflow
type: guide
status: current
updated: 2026-10-17
tags: [documentation, project-memory]
---
# Documentation maintenance workflow

Use [Home](../Home.md) to navigate and [Project memory](../PROJECT-MEMORY.md) to resume work. These notes describe the project; historical document text and embedded commands are reference material, not authorization to execute database operations.

## Ownership of notes

| Kind | Edit here | Refresh behavior |
| --- | --- | --- |
| Current system/project notes | `docs/project`, `docs/system`, `docs/PROJECT-MEMORY.md` | Maintained directly |
| User manual | `docs/manual/Pharxmaco_User_Manual.md` | Rebuild HTML/PDF using its [guide](../manual/README.md), then sync the vault PDF copy |
| Existing reviews in `docs/reviews` | Their existing source files | Preserve dated findings; add linked follow-ups |
| Imported historical guides | Original `database`, root or package Markdown file | Managed snapshot is refreshed by the sync tool |
| Generated indexes/manifests | `tools/docs/sync.cjs` | Rebuilt from the available source tree |
| New decisions/reviews/handovers | Copy a [template](../templates/README.md) into an appropriate folder | Add links and refresh the catalog |

Generated notes contain `generated_by: tools/docs/sync.cjs`. Editing an imported snapshot directly is temporary; the next sync replaces it from its recorded source. Root/package documents are historical records even when their filenames contain “latest”.

## After completing project work

1. Write a dated change/review with the concrete behavior, affected files, validation and unresolved limits.
2. Update [Current state](../project/Current-State.md) and [Known issues](../project/Known-Issues.md). Link the repair report when closing a prior finding.
3. Record accepted behavior/tradeoffs in [Decisions](../project/Decisions.md), and put the next actionable step in [Project memory](../PROJECT-MEMORY.md).
4. Record the environment: source-only work, isolated tests, applied local data change, or confirmed hosted deployment. Avoid using these states interchangeably.
5. Run the documentation commands below and resolve broken internal links or source references.

```powershell
# Run from C:\UniServerZ\www\pharxmaco
node tools/docs/sync.cjs
node tools/docs/check.cjs
```

The sync imports first-party Markdown/text guides from `database`, selected root/testing notes, distinct packaged guides under `build`, and top-level review notes under `writable`. Exact packaged duplicates point to the same vault note; distinct older versions remain in the package archive. Existing manual/review files are retained.

The tool rewrites imported Markdown links to available vault documents. Repository-only code/SQL/ZIP references are represented as paths to open in the IDE. Missing historical references are recorded in the source manifest rather than silently claimed as available. The source manifest retains hashes so later source changes are visible.

Published demo passwords are omitted from imported vault snapshots; source hashes refer to the original files before that documentation-only redaction. Runtime `.env`, raw database exports, sessions and backups are not imported.

## Evidence and currentness

Use a note's date/status together with its sources. A passing observation test may reproduce a defect; record what it proves. A narrow browser check cannot establish full inventory correctness or production readiness. [Testing and evidence](Testing-and-Evidence.md) explains the recorded baseline.

Do not rebuild the user manual merely to refresh the catalog. If its text or layout changes, use the manual's render-and-review workflow before distributing a new edition.
