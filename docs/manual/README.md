# Pharxmaco user manual

The manual has 34 chapters across 37 A4 pages and covers the current administrator and cashier workflows. Numbered revision labels and the issue date are omitted from the cover, document information, HTML navigation and PDF footers.

- Printable reference: `output/pdf/Pharxmaco_User_Manual_2026-10-18.pdf`
- Browser reading copy: `docs/manual/Pharxmaco_User_Manual.html`
- Editable manuscript: `docs/manual/Pharxmaco_User_Manual.md`
- Obsidian vault PDF copy: [Pharxmaco User Manual](Pharxmaco_User_Manual_2026-10-18.pdf), refreshed from the verified output by `node tools/docs/sync.cjs`.

The PDF includes a cover, document control, linked contents, role-specific procedures, worked discount/cash examples, daily checklists, troubleshooting and a glossary. It describes application behavior and does not certify tax registration or replace pharmacy operating policies.

The current manual omits the "Information to give the administrator" section at the user's request. The last chapter is "Terms and definitions"; its obsolete troubleshooting reference was removed. It retains black text throughout, plain white grid tables, 11-point Calibri body text, 23-point Calibri Light chapter headings, 10-point PDF tables, six grouped parts and right-aligned monetary columns. PDF fonts are embedded; the HTML copy supports desktop and phone screens. Other operating instructions and chapter page numbers are retained. The original 17 October PDF remains available; prior editions are archived under `writable/system-manual/revisions`. See the [monochrome and section-removal record](../reviews/2026-10-18-user-manual-monochrome-update.md) and [earlier design update](../reviews/2026-10-18-user-manual-design-update.md).

## Maintain a new edition

Edit the Markdown manuscript. Its page comments identify the chapter, title and intended role. The HTML and PDF are generated outputs; edit the builder only for layout changes. Rebuild from the project root using:

```powershell
& 'C:\UniServerZ\core\php83\php.exe' writable/system-manual/build.php
node writable/system-manual/verify.cjs --workflow-update
powershell.exe -NoProfile -ExecutionPolicy Bypass -File writable/system-manual/render-pages.ps1 -Pages all
node writable/medicine-type-review/check.cjs --manual-design-only
node tools/docs/sync.cjs
node tools/docs/check.cjs
```

Keep the expected page count and contents in sync when adding or rearranging chapters. Inspect every rendered page after a content/layout change; checking text alone does not verify the visual result.

The build uses local PHP/Dompdf and does not bootstrap the application or connect to the database. It reads the document identifier from the manuscript and retains the existing PDF filename. Calibri is loaded from the installed Windows fonts and embedded in the PDF; DejaVu Sans is the fallback on machines without Calibri. Recheck pagination after changing fonts or build environment.

The document verification record is `writable/system-manual/checks.json`; responsive HTML results are in `writable/system-manual/design-browser-checks.json`. Current samples are under `tmp/pdfs/manual-no-issue-date-2026-10-18`; the earlier complete monochrome render is under `tmp/pdfs/manual-monochrome-2026-10-18`. These and text extraction under `tmp/pdfs/system-manual` are review intermediates, not part of the distributed manual.
