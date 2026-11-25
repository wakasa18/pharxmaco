---
title: User manual design update
type: review
status: completed
updated: 2026-10-18
tags: [manual, documentation, typography, verification]
---
# User manual design update

> [!info] Historical Revision 1.1 record
> The later [monochrome and section-removal update](2026-10-18-user-manual-monochrome-update.md) replaces the palette/table styling and removes the requested administrator-information section and numbered revision labels. The top-level output, HTML and verification files describe the current manual. Revision 1.1's PDF, source, builder, manifest and checks are archived under `writable/system-manual/revisions/rev-1.1`.

Revision 1.1, issued 18 October 2026, refreshes the manual's design and fonts at the user's request. It retains 34 chapters across 37 A4 portrait pages. The [operating manuscript](../manual/Pharxmaco_User_Manual.md) is unchanged apart from revision and issue-date metadata; this update does not revise system procedures.

## Design changes

| Element | Revision 1.1 treatment |
| --- | --- |
| Typography | 11-point Calibri body, 23-point Calibri Light chapter headings and 10-point PDF tables |
| Cover | Text wordmark, clear title and subtitle, edition, six-part overview and document/revision/date fields |
| Navigation | Six parts in a two-column linked contents; chapter numbers and printed page references retained |
| Page hierarchy | Part/audience labels, lighter titles, running part names, blue section rules and consistent footers |
| Tables | Subtle horizontal rules/shading, adjusted column widths and right-aligned monetary values |
| Browser copy | Roomier screen line spacing and stacked contents columns on small screens |

The palette uses navy text with restrained blue accents and pale gray-blue table/callout fills. Fonts are embedded as subsets in the PDF. The builder uses installed Windows Calibri with a DejaVu Sans fallback; font changes on another machine require a fresh pagination check.

## Verification

- **Eight document checks passed:** 37 pages, correct chapter/page mapping, 68 linked-contents annotations, correct footer numbers, retained chapter endings, body/footer clearance, checked training examples, embedded typography and retained operating text are covered by the verifier's grouped checks.
- **Seven HTML browser checks passed** at 390, 820 and 1440 pixels: document links, typography, table/page overflow, contents navigation and print styling.
- Opened and visually checked **all 37 final PDF page images**, plus desktop/mobile HTML samples. No clipping, overlapping tables or footer collisions were found. The lowest body baseline is 98.75 points above the page bottom.
- The final PDF SHA-256 is `41ed693ea59690dd5165222aafc65a4e6d97dbb1dd0f445e446121b3cc343b84`.

These checks concern the manual output. No application records were changed, no database was accessed and the full application test suite was not rerun. Screenshots, tighter prose, exact UI wording and fuller release identification remain recommendations from the [earlier format review](2026-10-18-user-manual-format-review.md).

## Files and maintenance

The [current printable manual](../manual/Pharxmaco_User_Manual_2026-10-18.pdf) is copied into the Obsidian vault from `output/pdf/Pharxmaco_User_Manual_2026-10-18.pdf` by `node tools/docs/sync.cjs`. The [HTML reading copy](../manual/Pharxmaco_User_Manual.html) and manuscript remain editable/generatable through the [manual maintenance workflow](../manual/README.md).

Build and verification code is in `writable/system-manual/build.php`, `verify.cjs`, `render-pages.ps1` and `check-design.cjs`; the browser harness uses the `--manual-design-only` mode. Results are in `manifest.json`, `checks.json` and `design-browser-checks.json` in that folder. Final page images are under `tmp/pdfs/manual-design-2026-10-18`.

The original `output/pdf/Pharxmaco_User_Manual_2026-10-17.pdf` is preserved. Its source, builder and verification records were saved in `writable/system-manual/revisions/rev-1.0` before the redesign.

Related: [Project memory](../PROJECT-MEMORY.md) · [Current state](../project/Current-State.md) · [Testing and evidence](../development/Testing-and-Evidence.md).
