---
title: User manual monochrome update
type: review
status: completed
updated: 2026-10-18
tags: [manual, documentation, typography, verification]
---
# User manual monochrome update

> [!info] Current manual
> The monochrome design remains in use. The follow-ups below record subsequent user-requested changes: [section removal](#requested-section-removal), [revision-label removal](#revision-labels-removed) and [issue-date removal](#issue-date-removed). Current outputs omit numbered revision labels and the issue date.

Revision 1.2 applies the user's request to change the table design and remove colored fonts. The [current printable manual](../manual/Pharxmaco_User_Manual_2026-10-18.pdf) and [HTML copy](../manual/Pharxmaco_User_Manual.html) now use black text throughout, including headings, links, part labels, cover text, running headers and page numbers.

Tables use white backgrounds, thin neutral grid borders and bold black headers. Alternating blue-gray fills are removed. Callouts have a black side rule on white, and other decorative rules use neutral gray or black. Calibri typography, six-part contents, numeric alignment, 34 chapters and 37 A4 pages are retained. Operating instructions are unchanged apart from revision metadata.

## Verification

- Nine document checks passed, covering pagination, chapter endings, footers, linked contents, required topics, training examples, font embedding, retained operating text and black text on every PDF page.
- Eight HTML checks passed at 390, 820 and 1440 pixels, including computed black font colors, unshaded grid tables, overflow checks, contents navigation and print styling.
- Rendered all 37 pages at 1200 pixels wide. Reviewed every page in 10 overview sheets, with full-size inspection of the cover, document information, contents, benefit example, drawer example and product-classification pages. Desktop/mobile document-information screenshots were also reviewed. No clipping, overlap or footer collisions were found.
- The lowest body baseline is 98.55 points above the page bottom. The PDF SHA-256 is `e6da7f0bc4329e0e2210932b14a4dc31d5f7d62cb115d243ab1464925874e412`.

The same 18 October output filename now contains Revision 1.2. Revision 1.1's PDF, manuscript, builder and verification records were archived in `writable/system-manual/revisions/rev-1.1` before replacement. The original 17 October PDF is preserved. Current renders are in `tmp/pdfs/manual-monochrome-2026-10-18`; current results are in `writable/system-manual/manifest.json`, `checks.json` and `design-browser-checks.json`.

This task changed document styling only. No application data was modified, no database was accessed and no application test-suite result is claimed.

## Requested section removal

Removed the "Information to give the administrator" heading and its three paragraphs from the manuscript, HTML and PDF. Chapter 34 is now "Terms and definitions," and the contents was regenerated. The reference in Chapter 33 directing users to that removed section was removed. Other operating text is retained, as checked against the archived Revision 1.2 manuscript with those specific edits allowed.

Nine document checks and eight HTML browser checks passed. The PDF retains 37 pages, 34 chapters and 68 contents-link annotations, with black text throughout. Rendered and inspected pages 1, 2, 3, 36 and 37 after rebuilding; no clipping or layout defects were found. Samples are under `tmp/pdfs/manual-section-removal-2026-10-18`. The current PDF SHA-256 is `c6767247f9bd2cc26677dd31906b76a3d0ccfae47bacc7daf643b2dbee00a411`.

Revision 1.2's PDF, source, builder, manifest and checks were archived under `writable/system-manual/revisions/rev-1.2` before replacing the current 18 October output. No application data or database settings were changed.

## Revision labels removed

Removed numbered revision labels from the cover, document-information table, HTML navigation and every PDF footer. The cover now shows only document identifier and issue date; the information table uses "Issue date" and "Document owner." The builder reads the document identifier and date without requiring a revision number, and current manifest/check files no longer store a revision number.

Nine document checks and eight HTML checks passed. The removed labels are absent from the manuscript, HTML and text extracted from all 37 PDF pages. Rendered and inspected pages 1, 2, 20 and 37 with no layout defects. Samples are under `tmp/pdfs/manual-no-revision-2026-10-18`. The current PDF SHA-256 is `a9fdb231a8065dbc5f94122fcf27dd158729961e057164b72a65d354416b3b1c`. Application data was not accessed or changed.

## Issue date removed

Removed the issue date from the cover, document-information table, HTML navigation and every PDF footer. The information-table date row is deleted, and the cover/footer now show only the document identifier. Updated the instruction to check the issue date to say "Use a current copy when application screens change." The builder no longer requires or stores an issue date; the existing download filename remains stable.

Nine document checks and eight HTML checks passed. The issue date and its label are absent from the manuscript, HTML and extracted text on all 37 PDF pages. Rendered and reviewed pages 1, 2, 20 and 37 without layout defects. The lowest body baseline is 119.685 points above the page bottom. Samples are under `tmp/pdfs/manual-no-issue-date-2026-10-18`; current PDF SHA-256 is `9abab5655f95cece61a0bfe72e0e4f20a628f2548edc75aa98c1f51ab4f469a9`. No application data was accessed or changed.

## Intended users simplified

Changed the intended-users row to **Cashiers and administrators**, removing the designated-supervisors wording requested by the user. The PDF and HTML were rebuilt. Nine document checks passed; page 2 was rendered at 1200 pixels wide and visually reviewed with no layout defects. The generated HTML row was also checked. No additional browser or application tests were run for this text-only edit.

The PDF retains 37 pages and 34 chapters. Current PDF SHA-256: `44b477933d00d0cecf4004d01c3da3ef761c520050d2e50ff53eb93fd26412bb`. The page image is under `tmp/pdfs/manual-intended-users-2026-10-18`. No application records were accessed or changed.

Related: [Manual maintenance](../manual/README.md) · [Earlier design revision](2026-10-18-user-manual-design-update.md) · [Format review](2026-10-18-user-manual-format-review.md) · [Project memory](../PROJECT-MEMORY.md).
