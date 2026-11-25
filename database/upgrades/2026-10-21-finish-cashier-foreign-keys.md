# Complete an interrupted cashier FK import

Use **`2026-10-21-finish-cashier-foreign-keys.sql`** for the confirmed hosted state: `cashier_foreign_keys = 24`, `migration_000017 = 0`.

1. In phpMyAdmin, open **Import** and select this completion file. Do not rerun the full existing-database upgrade for this state.
2. Check the final result: `checked_database = u315645644_pharxmacotest`, `verified_foreign_keys = 24`, `migration_000017 = 1`, `upgrade_status = Upgrade complete`.

The file explicitly names `u315645644_pharxmacotest`. It works even if phpMyAdmin has switched its selected database to `information_schema`. It verifies each expected child column, parent table/column and restrictive delete/update action. It adds the missing migration row only when all 24 definitions match. It contains no ALTER, DROP, DELETE or business-record updates. Reimporting a completed state makes no changes.

If verification is below 24, the file refuses to record completion and reports **NOT COMPLETE**. It does not repair or remove constraints. The separate `2026-10-21-cashier-upgrade-status.sql` is read-only and also uses explicit schema names.

The screenshot's `SHOW PROCESSLIST` showed `information_schema` as the active connection database and no import query running. The initial FK count used `DATABASE()` and was therefore ambiguous. The corrected explicit-schema query then confirmed 24 existing FKs and no migration record. A blank import page alone does not establish which statements ran or why the request ended.

The full upgrade/helper has also been revised: four small metadata caches per pass replace repeated catalogue joins, FK additions are grouped into at most nine ALTER statements, and metadata-lock waits are limited to ten seconds. Those changes are for future/full or partial schema upgrades. For the confirmed **24/0** state, use this completion file only.

Verification: **49 isolated MySQL checks**, including the completion-only case, refusal at 23 keys, refusal with an incorrect cascade policy, repeat import, and operation while `information_schema` is selected; **62 PHP cases / 680 assertions**; and **11 supplied-export checks** passed. No hosted or local application records were changed during this recovery preparation. Execution on the host remains the user's import step.
