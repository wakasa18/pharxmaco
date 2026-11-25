---
title: Damaged Items page
type: implementation-review
status: implemented-locally
updated: 2026-10-23
tags: [inventory, damaged-items, returns, audit]
---
# Damaged Items page

## Result

Added administrator **Inventory → Damaged Items** (`admin/damaged-items`) and per-return **View details** pages, styled to match Quarantine. Both inventory pages have a link to the other. The list provides branch/source filters, filtered record/unit totals and DataTables search, sort, page length and pagination. It presents existing returned product lines, without fabricating stock movements or damage records.

Sources are:

- **Refund:** original return condition is `damaged` and no linked exchange event exists.
- **Exchange:** original return condition is `damaged` and an exchange invoice links the same original sale, return event and branch.
- **Quarantine inspection:** original condition remains `quarantined` and its later inspection outcome is `damaged`.

Refund/exchange source classification uses `ExchangeRecord::identity` and a batch lookup of linked invoices, not a reason-text prefix. An unrelated branch's exchange invoice cannot relabel a return. Quarantine outcomes use the recorded inspected quantity, inspector, inspection date and note; the original return reason/date/condition remain visible separately. Every eligible return line appears once.

Details link to the original sale, exact return-event slip (or timestamp for legacy returns), replacement receipt for an exchange, and the quarantine inspection where present. Missing records return 404. Inactive/deleted products and missing current branch inventory do not hide historical damaged returns. Displayed text is escaped.

The admin route filter provides live session/account revalidation; the read service also checks for an active administrator. Forged source/branch arrays, invalid sources and out-of-range identifiers are rejected. There is no damaged-item POST endpoint, release control or stock adjustment form.

## Database and scope

No migration, new SQL patch, schema change or data write is needed. The reader queries existing `refund_items`, `sales`, `products`, `branches` and `users`, optionally joining `quarantine_resolutions`. If update 000018 is absent, direct damaged returns remain available with an administrator notice about missing inspection outcomes.

The page is a damaged-return ledger. It does not infer damage from a generic inventory adjustment's text, record disposal/supplier credits, or allow damaged stock to be resold. Viewing it does not deduct available stock again; returned damaged units already remained outside available stock. Original refund/financial records are unchanged.

## Files and deployment

- `app/Libraries/DamagedItems.php`: read queries, authorization, source classification and detail selection.
- `app/Controllers/Admin/DamagedItemsController.php`: GET list/detail handling and exact slip links.
- `app/Views/admin/damaged_items/index.php` and `show.php`; `public/assets/css/damaged-items.css`: responsive list/detail UI using existing Quarantine styles.
- `app/Config/Routes.php`, `app/Views/layouts/sidebar.php` and Quarantine index: navigation and two admin GET routes.
- `tests/unit/DamagedItemsCases.php` and `tests/integration/damaged-items.php`: regression and read-only MySQL checks.

Prepared `build/hostinger-damaged-items-2026-10-23.zip`, including current Quarantine runtime/dependency files, the new Damaged Items files, the existing Quarantine SQL/guide, this update's deployment README and a SHA-256 manifest. See [deployment instructions](../development/Damaged-Items-Deployment.md). This avoids publishing shared navigation/routes without their new controllers. No hosting upload/import was performed.

## Evidence

| Check | Result and boundary |
| --- | --- |
| Focused PHP | 15 cases / 182 assertions pass: seven new damaged-ledger cases plus eight Quarantine cases. Mixed-source membership, no duplicates/writes, original/quarantine condition preservation, branch/source filtering, exact exchange identity, missing schema/inventory, authorization, invalid filters, HTML escaping, event/timestamp links and empty results. |
| MySQL | Seven read-only checks pass against local MySQL. List/quantity matches source criteria, source filters partition the list, branch/detail results match, and all 27 table hashes/migration rows remain unchanged. One existing damaged return / one unit found. No fixture/schema/application writes. |
| Browser | 61 checks pass with real rendered fixtures/assets in headless Chrome. Search/sort/pagination, record/unit totals, three sources, direct event/receipt/inspection links, empty/legacy schema states, no damaged-item POST form, 320/390/768/1440 widths in light/dark, no page overflow or JavaScript exceptions. Desktop/mobile screenshots reviewed. |
| Syntax | Nine changed/new PHP runtime/test files parse. |

Commands:

```powershell
$env:PHX_DAMAGED_FIXTURES='1'
& 'C:\UniServerZ\core\php83\php.exe' -d extension=sqlite3 vendor\bin\phpunit tests\unit\BusinessLogicTest.php --filter '::testDamaged|::testQuarantine' --no-coverage
& 'C:\UniServerZ\core\php83\php.exe' tests\integration\damaged-items.php
node writable\damaged-items-review\check.cjs
```

Artifacts are under `writable/damaged-items-review`: `focused-junit.xml`, `mysql-results.json`, `browser-results.json`, screenshots and package verification. Unit and browser fixtures contain synthetic records outside the application database.

The broader business suite was not repeated for this read-only addition. Its latest [Quarantine checkpoint](2026-10-23-quarantine-page.md) remains 248 cases: 237 pass, nine fail and two error in existing inventory safeguards. This update does not claim to repair those workflows.
