---
title: Deployment and package freshness
type: operations-guide
status: current
updated: 2026-10-28
tags: [deployment, hosting]
---
# Deployment and package freshness

The project uses separate application archives and database import patches. The existing deployment guides describe specific dated releases; a package named “latest” may still precede later source changes. The documentation vault is not a deployment bundle.

## Current security audit

The [27 October audit](../reviews/2026-10-27-whole-system-audit.md) confirmed unauthenticated local Apache access to project-root .env/SQL/ZIP/documentation paths. Only the public directory should be served; uploading the whole project into a served folder can expose private siblings despite using /public in the application URL. Actual hosting was not checked. Bundled framework 4.7.0 and Dompdf 3.1.5 also match current published advisory ranges. Resolve layout and component patching before production sign-off; runtime files were not changed by the audit.

The [selected audit repair package](Selected-Audit-Repairs-Deployment.md) contains 13 changed runtime files plus 11 current helper dependencies. No new SQL is required on the matching existing schema. It does not change server layout, framework/dependency versions or the unselected audit findings.

## Identify the intended release

1. Inspect the current source and the matching dated [upgrade/release guide](../upgrades/README.md).
2. Compare the archive's entries/hashes with the files intended for upload. Recent Cashier Shifts/DataTables changes and the user-name patch were made after earlier cashier/PDF packages.
3. Check the target database's actual baseline and import only the appropriate schema/data patch. A full export is not an additive upgrade.
4. Keep a record of the selected package, SQL file, target environment, backup and observed deployment result.

Existing packaging scripts include `writable/cashier-day-review/package.cjs` and `package.php`. Their presence does not prove an old ZIP was rebuilt after the most recent change.

## Configuration and data

Use the host's own `.env`, database credentials, seller invoice settings and writable data. Application file replacement and database record changes are separate actions. A data update applied to `pharxmaco_drugstore` locally does not change the hosted database.

The **17 October user-name patch** targets known placeholder full names and preserves custom names. The **16 October shift reset** deliberately clears shift history; it is not a routine schema upgrade. See [Database and recovery](../system/Database-and-Recovery.md).

## Historical hosting review

The [September hosting review](../archive/project/HOSTINGER-DEPLOYMENT.md) records public/private layout advice, earlier dependency advisories, a production environment template and staging checks. Treat its dependency audit/version thresholds as historical. A fresh dependency audit and actual host verification are required to establish the state of a later release; this documentation work performed neither.

Verify routes, assets, login/session persistence, role boundaries and representative money/inventory workflows on the intended staging host. Record the result rather than marking a release deployed based only on files existing in `build`.

Related: [Current state](../project/Current-State.md) · [Known issues](../project/Known-Issues.md) · [Change template](../templates/Change.md).
