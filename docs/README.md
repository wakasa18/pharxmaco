---
title: Using the documentation vault
type: guide
status: current
updated: 2026-10-17
tags: [documentation, obsidian]
---
# Using the documentation vault

In Obsidian, choose **Open folder as vault** and select `C:\UniServerZ\www\pharxmaco\docs`. Open [Home](Home.md), then bookmark it if desired. The notes use ordinary Markdown links, YAML properties and local attachments; community plugins are optional.

| Folder | Purpose |
| --- | --- |
| `project` | Current checkpoint, decisions, issues, changes and research boundaries |
| `system` | Feature behavior and the code responsible for it |
| `manual` | Editable user manual, HTML reading copy and printable PDF |
| `reviews` | Existing audits, repair reports and their index |
| `upgrades` | Vault copies of dated deployment/database guides |
| `data-guides` | Existing sample-data and export explanations |
| `evidence` | Existing implementation and manual-review notes |
| `reference` | Generated catalog, route inventory, SQL inventory and source manifest |
| `archive` | Older root documentation and distinct packaged versions |
| `development` | Documentation, testing and deployment maintenance |
| `templates` | Starting points for decisions, reviews, changes and handovers |

Use Obsidian's backlinks or graph to follow related notes. Imported historical documents have a source path, source hash and snapshot status. Their links to other imported guides resolve inside the vault. References to repository code, SQL or ZIP files are shown as repository paths to open in the IDE.

Maintain current notes directly. Refresh imported guides and generated indexes from the **project root**:

```powershell
node tools/docs/sync.cjs
node tools/docs/check.cjs
```

The sync refreshes managed snapshots, catalogs and the PDF copy. It does not execute SQL or connect to the application database. See [Documentation workflow](development/Documentation-Workflow.md) before editing generated notes.

Published demo passwords are omitted from vault snapshots. Original operational guides remain in the repository; `.env`, database dumps, sessions and backup records are outside this vault.
