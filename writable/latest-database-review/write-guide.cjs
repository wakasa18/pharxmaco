const fs=require('node:fs');
const manifest=JSON.parse(fs.readFileSync(__dirname+'/manifest.json','utf8'));
const verification=JSON.parse(fs.readFileSync(__dirname+'/verification.json','utf8'));
const credentials=JSON.parse(fs.readFileSync('writable/demo-user-review/credentials.json','utf8'));
const text=`# Existing database upgrade for phpMyAdmin — October 15, 2026

Use **2026-10-15-latest-existing-database.sql** with the existing database exported as **u315645644_pharxmacotest (4).sql**. It adds the remaining current updates to that database. It is an upgrade patch, so importing it does not restore duplicate copies of backup records.

## Import in phpMyAdmin

1. Export a backup of the current database and finish any transactions in progress.
2. Select your existing application database, **u315645644_pharxmacotest**, in phpMyAdmin's left sidebar.
3. Open **Import**, select **2026-10-15-latest-existing-database.sql**, keep the format as **SQL**, then choose **Go / Import**.
4. Wait for the successful import result and the final **Latest database upgrade complete** result. If an SQL error occurs, stop and use the exact error message to resolve it.
5. Use the latest application files from **build/hostinger-cashier-workspace-2026-10-15.zip** if they have not already been uploaded. Start Shift routing, My Day and the Review buttons are application features; SQL supplies their database structure.

## Included changes

The supplied export already has updates **000010–000013**: delivery/expiry snapshots, invoice fields and numbering, product medicine type and Senior Citizen/PWD fields. Tax classifications are retained. This file also includes the newly requested product catalog changes: cleaned names, **4 Branded**, **35 Generic** and **15 Non-medicine** records; **31 additional medicines** become eligible, while **14 records stay Not confirmed**. The existing bandage eligibility is preserved. See **2026-10-15-product-catalog.md** for the full mapping, product uncertainties and official references. Matching application files add the Non-medicine display/filter choice.

This file adds **000014**, with seven cashier tables for shared drawers, shifts, financial events, held sales, shift reviews, event reviews and shift actions. Each of the two existing branches receives one Main drawer. No historical shifts, cash counts, demo transactions or sale snapshots are fabricated.

It also adds **12 missing branded medicine variants** with the requested demo inventory: **50 units each (600 units total)**, sample unit prices/costs and an expiry one year after import. They are assigned to the active Main Branch, falling back to the first active branch. After the full import, the supplied export contains **66 products: 16 Branded, 35 Generic and 15 Non-medicine**. See **2026-10-15-branded-products.md** for names, prices and official formulation references. Demo prices and expiry are example values for testing; replace them with verified values for live stock.

The three previously requested demo accounts are added only if their usernames do not already exist:

| Username | Role | Branch |
|---|---|---|
| \`demo_admin\` | Administrator | All branches |
| \`demo_cashier1\` | Cashier | Active Main Branch |
| \`demo_cashier2\` | Cashier | Same branch |

Password for all three newly created accounts: **\`${credentials.password}\`**.

If Main Branch is not active, the demo seed uses the first active branch. No demo account is created without an active branch. Matching existing usernames are skipped: passwords, roles, branch assignments and inactive status are never reset. These are normal accounts with normal permissions; sales made with them affect the selected database.

## Preserved data and verification

Verified using the complete supplied export in an isolated local MySQL database:

- All **${verification.original_counts.products} products**, **${verification.original_counts.sales} sales**, **${verification.original_counts.sale_items} sale items** and **${verification.original_counts.users} original users** were retained. Only the five reviewed product fields change: name, generic name, strength, medicine type and benefit eligibility.
- Stock, prices, product tax classifications/timestamps, beneficiary/invoice metadata, payments, refunds, audit logs and invoice sequence data were preserved.
- Twelve new branded products, twelve new branch inventories and twelve clearly labeled demo stock-in logs were added. Existing inventory and stock logs were unchanged. Reimporting never restocks the new products or resets edited prices/expiry.
- Users increased from **${verification.original_counts.users} to ${verification.upgraded_counts.users}** only because of the three missing demo accounts; migration history gained exactly update 000014.
- Importing the file a second time left all records unchanged.
- Later closing reports, handovers, review records, held carts, edited demo credentials/permissions, medicine classifications and invoice numbers also survived reimport unchanged.

The source export was preserved. SHA-256: \`${manifest.source_sha256}\`.

Verification did not modify the current local application database or your hosted database. Import this file into phpMyAdmin to update the hosted database.
`;
fs.writeFileSync('database/upgrades/2026-10-15-latest-existing-database.md',text,'utf8');
console.log('Wrote phpMyAdmin import guide and demo account details.');
