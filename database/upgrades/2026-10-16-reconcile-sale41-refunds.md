# Sale #41 test-refund correction

The owner confirmed that these are test records and their refunds should total PHP 500.00. The previous PHP 625.00 total refunded the pre-discount merchandise amount, creating a PHP 125.00 excess.

| Refund IDs | Original amount per row | Correct amount per row | Net unit price |
| --- | ---: | ---: | ---: |
| 3, 7 | PHP 100.00 | PHP 80.00 | PHP 40.00 |
| 4, 5, 6 | PHP 75.00 | PHP 60.00 | PHP 60.00 |
| 8 | PHP 200.00 | PHP 160.00 | PHP 160.00 |

The local database has already been repaired and verified. Refunds now total PHP 500.00, matching sale #41's saved net total, and no line exceeds its saved net value. Returned quantities, dates, reasons, stock, sale items, the invoice and cashier events were preserved. The activity log records the correction.

The original six refund records are preserved in the database table `phx_backup_20261016_sale41_refunds`. A local JSON snapshot also exists under `writable/backups/sale41-refunds-20261016-110240-e8c7a040.json`.

To repair the matching hosted test data:

1. Export a backup in phpMyAdmin and pause refunds.
2. Select the existing application database, then import **`2026-10-16-reconcile-sale41-refunds.sql`** in full.
3. Confirm the final result reports `total_refunded = 500.00` and `refund_excess = 0.00`.

The script checks invoice `INV-20260617103518-97E5EE`, all three sale items, all six original refund records, an available administrator and the absence of cashier events for this sale. If they differ, it skips the monetary changes and reports that review is needed. Original rows are backed up before applying the correction. Repeating the import skips already-corrected records without adding another audit entry. Stop on any SQL error and roll back the transaction before retrying.

This file is only for the confirmed test records identified above. It is separate from the schema/catalog upgrades and master-stock reconciliation.
