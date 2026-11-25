# Latest upgrade for the existing database

Import **2026-10-14-latest-existing-database.sql** into the existing application database in phpMyAdmin. Select the database, open **Import**, choose this SQL file, and run the complete file. It is an upgrade patch, not a replacement database dump. Use it with the latest application files.

Prepared against `u315645644_pharxmacotest (3).sql`, exported October 14, 2026. The source is preserved (SHA-256 `6ab0d7836c2493c6814a3b8175c5f82fd8f15c10982d6647029cf6d0a779b0fe`).

Updated October 15: the file combines missing migrations `2026_10_04_000010` through `2026_10_15_000014`: 14 missing columns for stock expiry tracking, invoice fields, branded/generic medicine type and Senior Citizen/PWD benefits, plus the invoice sequence table and seven cashier workspace tables. Each branch receives one shared drawer; historical shifts and opening floats are not invented. Use the matching October 15 application package. See `2026-10-15-cashier-workspace.md` for daily operation.

The product patch fills 39 identifiable generic names and 27 strengths where fields remain empty. It matches each product by ID, name and SKU. All current products are marked VATable, as previously requested. Existing prices, stock, timestamps, accounts, payments and sale history are preserved. The file contains no backup-row inserts, deletion commands or database-switching commands.

Existing medicine type and benefit eligibility values are retained. Newly added fields start **Not specified** and **Not confirmed**; confirm them under **Products → Edit** before using them. No historical invoice, beneficiary, delivery, medicine or tax snapshots are invented. Registration details and invoice numbering are configured in `app/Config/ReceiptInvoice.php`, following the existing invoice setup guide.

Verified on an isolated local MySQL database using the supplied dump: all 54 products, 776 sales and 1,326 sale items retained their original field values. The upgrade imported successfully twice without changes on the second pass. Additional verification confirmed staff-entered medicine details, beneficiary/invoice metadata, saved line snapshots and an existing invoice sequence survive reimport. The application database was not modified by this verification.
