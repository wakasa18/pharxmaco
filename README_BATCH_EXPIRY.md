# Batch-level expiry tracking — install notes

Every delivery is now its own batch (lot) with its own expiry date and quantity.
Sales take the batch that expires first and never sell expired batches. Returns go
back into the batch they were sold from.

## Install (run from your project root, the folder that contains `app/` and `public/`)

```bash
# 1. Back up the database and the code first
mysqldump -u YOUR_DB_USER -p YOUR_DB_NAME > backup_before_batches.sql
cp -r app app_backup_before_batches

# 2. Extract the patch over the project (adds new files, replaces the changed ones)
unzip -o batch-expiry-patch.zip

# 3. Upgrade the database BEFORE using the new code (pick ONE of the two)
php spark migrate                                                      # CodeIgniter migration
# or, without spark (phpMyAdmin "Import" works too):
mysql -u YOUR_DB_USER -p YOUR_DB_NAME < database/upgrades/2026_11_20_add_product_batches.sql

# 4. Clear caches
php spark cache:clear
rm -f writable/cache/*.cache 2>/dev/null

# 5. Check: both numbers must be equal
mysql -u YOUR_DB_USER -p YOUR_DB_NAME -e "SELECT (SELECT SUM(GREATEST(stock,0)) FROM branch_products) AS branch_stock, (SELECT SUM(quantity_remaining) FROM product_batches) AS batch_stock;"
```

Both upgrade routes are safe to run twice. Run step 3 before the new code is live,
otherwise pages that read batches will error until the tables exist.

## What changed for staff

- **Add stock** (Products list, cashier): enter the delivery's batch/lot number (optional) and expiry date. Each add is a new batch; the same lot number and expiry adds to that batch.
- **Remove stock**: choose which batch to take from, or leave it on "Earliest expiry first".
- **New product / add existing product to a branch**: opening stock becomes the first batch (new optional "Batch / Lot Number" field).
- **Product edit**: stock and expiry are read-only for existing inventory (total and nearest expiry). Use Add/Remove stock, or the new **Batches** page (`/products/batches/{id}`). Admins can correct a lot number or expiry date there (reason required, logged).
- **POS / exchanges**: only unexpired batches count as available stock; a sale is filled earliest expiry first and can span batches. A sale line remembers its batches.
- **Refunds, quarantine release, exchange returns, admin sale corrections**: units go back to the batch(es) they came from; they are refused if that batch has expired.
- **Expiry report (screen, CSV, PDF), dashboard, notifications**: one row per batch with stock, with the lot number shown.
- **Stock logs**: one row per batch movement, showing the batch.

## Your existing stock

Each branch product's current stock was carried over as one batch ("Existing stock before batch tracking") using its current expiry date. 117 branch products became 109 batches totalling 5,595 units, matching current stock exactly. Add real lot numbers via the Batches page if you want them on the older stock.

## Good to know

- `branch_products.stock` is still the total on hand and `branch_products.expiration_date` the nearest expiry among batches with stock, so anything else reading those columns keeps working.
- If stock is ever changed outside the app (for example directly in the database), the next stock operation reconciles the batches to match `branch_products.stock`.
- Restoring a backup made before this upgrade leaves the batch tables out of step; the same reconciliation corrects it on the next stock operation, but run the upgrade script again after such a restore and review the Batches page.
- Dispose / Return / Replace in the expiry report now act on one batch (the report links pass the batch).
