---
title: Database updates and recovery
type: system-reference
status: current
updated: 2026-10-21
tags: [database, migrations, backup]
---
# Database updates and recovery

Schema migrations are in `app/Database/Migrations`; phpMyAdmin-ready patches are in `database/upgrades`. The latest source migration is cashier foreign-key update **000017**, following cashier workspace 000014, login ownership 000015 and presence 000016. A filename's presence does not prove the selected database has that schema; inspect its migration records and fields.

Update 000017 enforces 24 restrictive cashier, login and presence references after checking existing types and records. Nullable pointers remain optional. The local upgrade preserved all 25 existing data tables and prior migration rows. The explicit history reset now clears handover links before deletion, but installing FKs does not run a reset. [Verification and import guide](../reviews/2026-10-21-cashier-foreign-keys.md).

## Different kinds of database files

| File type | Intended use |
| --- | --- |
| Additive schema patch | Upgrade a compatible existing database after checking prerequisites |
| Product/demo seed | Add or update specifically scoped sample/catalog data |
| Targeted repair/reset | Change the stated records only; review its conditions and backup |
| Full SQL export | Restore into an empty compatible destination; avoid importing duplicate records into an existing database |
| Application `.pmbak` | Application backup/restore with schema compatibility checks |

“Latest existing database” files were prepared against specific supplied exports. They are not universal installers. The migration folder is incremental and is not a complete original-schema installer.

## Recent targeted patches

- `2026-10-16-reconcile-master-stock.sql`: reconcile derived product totals from branch balances; local repair was applied in its authorized task.
- `2026-10-16-reconcile-sale41-refunds.sql`: correct exact confirmed test refund records; local repair was applied in its authorized task.
- `2026-10-16-clear-cashier-shifts.sql`: clear shift history and detach its links while retaining sales/events; prepared for import, not applied locally during preparation.
- `2026-10-17-user-full-names.sql`: replace matching placeholder names; applied to seven local users and available for hosted import.

See [SQL inventory](../reference/Schema-and-SQL.md) for the full list and [upgrade guides](../upgrades/README.md) for prerequisites. Do not import every dated patch in order as a substitute for checking the actual baseline.

## Application backup and restore

The administrative route is `/admin/backup-restore`. `.pmbak` contains application database data and schema metadata; it does not include application code, `.env`, uploads or the whole server. Backup/restore uses the application's InnoDB and schema compatibility checks. The configured application size limit can be lowered by PHP/host limits.

Restore requires password/confirmation and creates a safety backup before replacing compatible data. It is a replacement workflow, not a merge. Credentials and sessions after restore must match the restored accounts. Physical stock, drawer cash and issued invoice counters still require reconciliation with the real world.

Code: `app/Libraries/DatabaseBackupService.php`, `app/Controllers/Admin/BackupRestore.php`. Procedures: user manual Chapter 30.

Restore now verifies every installed FK, including composite/optional-NULL references, before committing data loaded with checks temporarily disabled. Invalid references cause complete rollback and FK checking is restored. Existing schema compatibility checks still apply; retain a full SQL backup before schema upgrades and create a new application backup afterward.

Related: [Deployment](../development/Deployment.md) · [Known issues](../project/Known-Issues.md).
