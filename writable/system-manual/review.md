# Manual review record

The user requested a professional manual for the whole system. The printable PDF and editable Markdown were prepared from the current workspace, with a generated HTML reading copy.

## Source areas checked

- Routes and sidebar: role availability, navigation and the standalone backup/recovery route.
- Auth, AccountSession and account/branch controllers: sign-in destinations, session revocation, account status, password rules, branch scope and protected administrator access.
- SalesController and POS/sale views: cart, held orders, payment recording, interrupted checkout, receipts, history, beneficiary display and refund workflows.
- ProductPromotion, BenefitProductPromotion, DiscountPolicy and StatutoryDiscount: automatic product/category offers, manual all-products selection, inclusive offer dates, matching minimum/cap allocation, VAT relief and benefit selection.
- Exchange views, ProductExchange and SaleCorrection/SaleCorrectionPricing: return credit, settlement differences, replacement discount selection and correction restrictions.
- CashierShift, CashierDayReport and workspace views: start/close, shared drawers, handover, cash movements, review actions, recent activity limits and complete shift printing.
- Products and product forms: shared identity vs branch stock, SKU reuse, cashier catalog access, classifications, absolute count vs delta and stock audit entries.
- ExpiryStockResolution, ExpiryStockController and expiry views: full replacement semantics, disposal/supplier return, branch deactivation, supported roles and current single-expiry model.
- ReturnCondition, RetainedRecordDeletion and BranchProductTrash: resellability handling, physical segregation, branch-specific Trash and retained permanent hiding.
- Dashboard, ReportsController, ReorderForecastService, PDF/CSV views: differing report periods, forecast settings and saving, 30-day restock assumption, export filters, full/summary scopes and limits.
- ReceiptInvoice configuration/library, SystemSettings and receipt partials: seller configuration, registered-range numbering, metadata, branch address, thermal printing and unconfigured/historical values.
- DatabaseBackupService, BackupRestore and form: .pmbak format, structure compatibility, 256 MB/server limit, password/confirmation, safety backup, replacement semantics and post-restore verification.

## Original validation: Revision 1.0, 17 October 2026

The final PDF contains 37 pages and 34 chapters. All 34 chapter headings begin on their expected pages. All 68 contents links are present, page numbers are correct, every chapter retains its final paragraph on its own page, and the lowest body text baseline is over 88 points above the physical page bottom, clear of the footer. Discount example amounts were checked. All 37 final page PNGs were opened and reviewed for readability, table wrapping, clipping, page breaks and footer placement.

No sale, user, stock or other application record was created or modified. No live screenshot containing customer information was used. The document checks do not claim to revalidate the full application test suite or legal/tax compliance.

## Design validation: Revision 1.1, 18 October 2026

Updated the PDF and HTML typography and layout: Calibri 11-point body, Calibri Light 23-point chapter titles, 10-point PDF tables, navy/blue accents, six grouped parts, a new cover, running part headers and right-aligned monetary columns. Operating instructions are retained from the previous edition, apart from revision/date metadata. The original PDF and Revision 1.0 source/build/verification records are preserved.

The revised PDF still has 37 A4 pages, 34 chapters and 68 contents-link annotations. Eight document checks passed, including embedded TrueType fonts and unchanged operating text. Every chapter starts and ends on its expected page. The lowest body baseline is 98.75 points above the page bottom. All 37 final PNG pages were opened for visual inspection; no clipping, overlapping tables or footer collisions were found.

Seven HTML browser checks passed at 390, 820 and 1440 pixels, covering contents targets, typography, table/page overflow, navigation and print rules. Desktop/mobile cover, contents and benefit-example screenshots were reviewed. PDF output is `output/pdf/Pharxmaco_User_Manual_2026-10-18.pdf`; final page renders are in `tmp/pdfs/manual-design-2026-10-18`.

This work did not connect to the application database or change application records. It did not rerun the application test suite. Further wording edits and actual-system screenshots were outside this design/font update.

## Monochrome revision: Revision 1.2, 18 October 2026

The user requested a different table design and no colored fonts. All text is now black, including links, headings, cover labels, running headers and page numbers. Tables have white cells, thin neutral grid borders and bold headers. Blue accents and callout fills were removed throughout the PDF and HTML. The content and chapter references are unchanged apart from revision metadata.

Nine document checks passed, including a black-text check over every PDF page's text graphics state. Eight browser checks passed, including black text and unshaded grid cells for screen/print at 390, 820 and 1440 pixels. The PDF remains 37 A4 pages with 34 chapters and 68 linked-contents annotations; the lowest body baseline is 98.55 points above the page bottom.

Rendered all 37 pages at 1200 pixels wide. Reviewed all pages in 10 overview sheets, full-size pages 1, 2, 3, 11, 19 and 23, and desktop/mobile HTML samples of the revised document-information table. No clipping, overlap or footer collisions were found. Current renders are under `tmp/pdfs/manual-monochrome-2026-10-18`. Revision 1.1 was archived in `writable/system-manual/revisions/rev-1.1` before replacing the 18 October PDF. No application records or database settings were changed.

## Section removal, 18 October 2026

Removed "Information to give the administrator" and its three paragraphs at the user's request. Renamed Chapter 34 to "Terms and definitions" and removed Chapter 33's obsolete reference to the deleted material. Other operating text is unchanged, checked against the archived Revision 1.2 source with only those edits and edition metadata allowed. Revision 1.2 was archived under `writable/system-manual/revisions/rev-1.2` before replacement.

Nine document checks and eight HTML browser checks passed. The PDF remains 37 A4 pages with 34 chapters and 68 contents-link annotations. The removed section is absent from the manuscript and extracted PDF text, and all PDF text remains black. Pages 1, 2, 3, 36 and 37 were rendered at 1200 pixels wide and visually reviewed; no layout defects were found. These samples are under `tmp/pdfs/manual-section-removal-2026-10-18`. No application data was changed or database accessed.

## Revision labels removed, 18 October 2026

Removed numbered revision labels from all current manual outputs, including the cover, document information, HTML navigation and PDF footers. The builder now reads document identifier and issue date without requiring a revision number. The document owner label replaces revision owner. No operating instructions were changed.

Nine document checks and eight HTML checks passed; extracted text from all 37 PDF pages contains no numbered revision label. Rendered and reviewed pages 1, 2, 20 and 37 under `tmp/pdfs/manual-no-revision-2026-10-18`; no layout defects were found. Current PDF SHA-256: `a9fdb231a8065dbc5f94122fcf27dd158729961e057164b72a65d354416b3b1c`. No application data was accessed or changed.

## Issue date removed, 18 October 2026

Removed the issue date from the cover, document-information table, HTML navigation and all PDF footers. The document-information date row is deleted; cover/footer retain the document identifier. Updated the sentence directing readers to check the issue date to say "Use a current copy when application screens change." The builder no longer parses or stores an issue date, while retaining the existing download filename.

Nine document checks and eight HTML checks passed. Manuscript, HTML and extracted PDF text from all 37 pages contain no issue date or date label. Pages 1, 2, 20 and 37 were rendered at 1200 pixels wide and visually reviewed without layout defects. The lowest body baseline is 119.685 points above the page bottom. Samples: `tmp/pdfs/manual-no-issue-date-2026-10-18`. Current PDF SHA-256: `9abab5655f95cece61a0bfe72e0e4f20a628f2548edc75aa98c1f51ab4f469a9`. No application data was accessed or changed.

## Intended users simplified, 18 October 2026

The intended-users row now reads **Cashiers and administrators**, removing the designated-supervisors wording at the user's request. Rebuilt PDF/HTML and passed nine document checks; rendered and visually reviewed page 2 at 1200 pixels wide without layout defects. Also checked the generated HTML row. The manual retains 37 pages and 34 chapters; no additional browser or application tests were run for this text-only edit.

Page sample: `tmp/pdfs/manual-intended-users-2026-10-18/page-02.png`. Current PDF SHA-256: `44b477933d00d0cecf4004d01c3da3ef761c520050d2e50ff53eb93fd26412bb`. No application data was accessed or changed.
