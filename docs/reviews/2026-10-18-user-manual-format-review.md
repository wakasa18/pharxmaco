---
title: User manual format and editorial review
type: review
status: improvements-recommended
updated: 2026-10-18
tags: [manual, documentation, editorial-review]
---
# User manual format and editorial review

> [!info] Design follow-up completed
> This review records Revision 1.0. The subsequent [Revision 1.1 design update](2026-10-18-user-manual-design-update.md) changes fonts, cover, contents grouping and monetary alignment. The [Revision 1.2 monochrome update](2026-10-18-user-manual-monochrome-update.md) uses black text and unshaded grid tables. Operating text is retained; recommendations for wording, screenshots and fuller release identification remain open.

## Overall assessment

Revision 1.0 has the structure of a real software operating manual. Its cover, document identifier, linked contents, roles, numbered tasks, worked examples, troubleshooting and glossary are appropriate. The typography, tables, running headers and page numbering are consistent. A full visual redesign is unnecessary.

It still needs an editorial pass before being presented as a finished training manual. Some instructions are indirect, several titles need punctuation, the content is entirely text-based, and generic caveats recur throughout the book. These observations describe usability and writing; they do not establish who or what authored a document.

## Scope and checks

- Reviewed `output/pdf/Pharxmaco_User_Manual_2026-10-17.pdf`, its editable Markdown and HTML/build structure.
- Rendered all **37 A4 pages** at 1200 pixels wide. Reviewed the complete page-overview sheets, the last page and full-size detail samples covering front matter, procedures, tables, configuration and recovery.
- Rechecked all **34 chapter starts**, page-number labels, chapter-ending text and **68 contents-link annotations** using the existing document verifier.
- Read the manuscript's procedure structure and wording; counted approximately 11,000 words, **24 Important callouts**, **20 Expected result labels**, and **zero screenshot illustrations**.
- Checked the current Users and Products view labels for the examples below.

The PDF and manuscript were not revised or re-exported. This was a document review, not an application audit, a new full test run, an external comparison study or a legal/compliance review.

## Findings and recommended edits

| ID | Priority | Location | Finding and concrete improvement |
| --- | --- | --- | --- |
| MAN01 | High for training | Core procedures, especially pages 7–11, 15–16 and 20–21 | No screenshots or annotated interface examples show where actions are. Add selected actual-system screenshots for Start Shift, POS/payment, beneficiary selection, refund review and closing/handover. Crop to the relevant controls and use short numbered captions; avoid adding an illustration to every page. |
| MAN02 | High | Pages 22 and 28 | Instructions such as “Choose the product creation action” and “choose its create action” sound like drafting notes. Use the exact screen controls. Users currently shows **+ Add User**; Products shows **+ Add Product** for administrators and **+ Create New Product** for cashiers. |
| MAN03 | Medium | Throughout; examples on pages 18, 20 and 37 | Repeated explanatory contrasts and lengthy caveats interrupt task flow. Keep transaction-critical warnings beside the action, but consolidate repeated background limitations into a short operating-notes section. Preserve the practical distinction between a sign-out and a shift close. |
| MAN04 | Medium | Contents and corresponding headings, pages 3, 13, 18, 24, 27–28 | Titles need more natural phrasing/punctuation: “Hold resume and discard a sale” should use commas; “Read My Day correctly” can become “Review daily sales and cashier activity.” Prefer short task names and verify the contents after renaming. |
| MAN05 | Medium | Worked currency tables on pages 11 and 19 | Monetary columns are left-aligned. Right-align peso amounts, retain consistent two-decimal formatting and use tabular figures. Keep explanatory labels left-aligned. The arithmetic in the checked training examples is consistent. |
| MAN06 | Medium | Document control, page 2 | “Current workspace” does not identify a supported application release. State the actual release/build identifier or explicitly defined application snapshot date, and add a revision-history table as the manual evolves. Identify the responsible office/person and support contact when supplied; do not invent an approver. |
| MAN07 | Optional structural polish | Overall contents and administrator/technical chapters | Thirty-four equal-level chapters create a very uniform rhythm. Group them into parts such as Getting Started, Selling, Returns, Cashier Shifts, Inventory and Administration. Technical configuration paths and data-reset cautions can sit in a clearly marked administrator appendix. Preserve the short, consistent procedure format where it helps staff. |

The absence of screenshots alone is not evidence of poor authorship: text-only reference manuals are valid. Here, selected illustrations would make the many form-based tasks more useful to a new cashier and more specific to Pharxmaco.

## Examples of a tighter editorial style

| Existing wording | Suggested wording |
| --- | --- |
| “Open Users and choose its create action.” | “Open **Users**, then select **+ Add User**.” |
| “Create a genuinely new product” | “Add a new product” |
| “Read My Day correctly” | “Review daily sales and cashier activity” |
| “Current workspace with cashier shifts…” | Identify the actual supported release or application snapshot date. |

**Open**, **Before you begin** and **Expected result** are legitimate procedure conventions. Retain them when useful; making every section stylistically different would make the manual harder to scan. The goal is precise, product-specific wording rather than removing its professional structure.

## What passed

- No clipping, overlapping tables or footer collisions were identified in the reviewed rendering.
- Chapter starts, page numbers, contents links and chapter-ending paragraphs passed the existing checks.
- Heading hierarchy, page margins, table borders and alternating row shading are consistent.
- Instructions distinguish cashiers and administrators, and the worked discount/cash examples support training.
- No shared passwords are published in the manual.

The verifier places the lowest body-text baseline more than 88 points above the physical page bottom, clear of the footer. This geometric check supports the visual review; it does not replace proofreading or a trial with a new user.

## Recommended next edition

First replace vague UI references and tighten repeated prose. Then add a small set of verified screenshots, tidy titles/currency tables and complete document control. Rebuild the HTML/PDF together, update revision/contents/page expectations as needed, and render-check the whole revised edition before distributing it.

Original review renders: `tmp/pdfs/manual-format-review-2026-10-18`. Revision 1.0 verification/manifest records are preserved in `writable/system-manual/revisions/rev-1.0`; the top-level `checks.json` and `manifest.json` describe the current manual, which omits the requested administrator-information section and numbered revision labels. The original PDF remains unchanged.

Related: [Manual source](../manual/Pharxmaco_User_Manual.md) · [Manual maintenance](../manual/README.md) · [Documentation workflow](../development/Documentation-Workflow.md) · [Home](../Home.md).
