# Reports repairs — 28 October 2026

Fixed A11, A13 and A15 only. No live database or hosting access, schema/import or historical-record edits.

- Released quarantine inspections recover the original sale-item unit cost in both report totals and period history, once per refund line. Original refund condition remains quarantined. Pending, damaged and expired returns retain their cost loss. EXISTS prevents inspection rows from multiplying recovered cost. Missing quarantine table retains legacy resellable-only report behavior.
- The report deliberately restates the original sale-date cohort using current inspection outcomes. It is not a new cost-recovery journal on the inspection date. Screen, CSV and PDF explain that basis.
- CSV fully generates into a temporary stream, closes the resource, then records history and returns a download Response. CSV filename does not read the PDF-only mode. A forced generation exception creates no history or duplicate-suppression receipt; retry can succeed.
- PDF completes byte generation before history. Full uses sales_report_ and Summary uses sales_summary_. Neither history path proves client download completion.

Changed files are listed in results.json. Eight new cases /167 assertions pass;20 related cases /313 assertions pass. Actual application routes/admin filters return the three correct attachment formats and deny guests using the framework's isolated request client. No external HTTP service or application session was used.

PDF skill read and used for read-only inspection of generated application test output. Full3pages and Summary1page contain recovered cost12/profit18 and the explicit cost basis. All4pages rendered and visually inspected; page bounds and text checks pass (pdf-results.json). No document design overhaul was performed. Four changed PHP files pass syntax checks.
