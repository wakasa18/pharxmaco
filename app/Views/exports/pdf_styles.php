<style>
    /* Physical print units keep text readable at A4 / Actual size (100%). */
    @page { size: A4 landscape; margin: 19mm 12mm 16mm; }
    body { margin: 0; font-family: "DejaVu Sans", sans-serif; font-size: 9pt; line-height: 1.25; color: #182333; }
    .header { border-top: 2pt solid #6544b4; border-bottom: .5pt solid #b9c1cc; padding: 5pt 0; margin-bottom: 7pt; }
    .header h1 { margin: 0 0 3pt; font-size: 9pt; color: #49307e; letter-spacing: 1pt; text-transform: uppercase; }
    .header h2 { margin: 0 0 5pt; font-size: 17pt; line-height: 1.2; font-weight: bold; color: #172439; }
    .meta, .meta-table { width: 100%; table-layout: fixed; border-collapse: collapse; }
    .meta td, .meta-table td { border: 0; padding: 2px 8px 2px 0; vertical-align: top; word-wrap: break-word; }
    .meta, .meta-table { font-size: 9pt; }
    .meta-label { width: 80pt; color: #465264; font-size: 9pt; }
    .notice { border-left: 3px solid #c4922e; background: #fff8e9; color: #755217; padding: 8px 10px; margin: 10px 0; }
    .section-title { margin: 9pt 0 4pt; padding: 3pt 0; border-bottom: 1pt solid #b9c1cc; color: #352451; font-size: 11pt; font-weight: bold; page-break-after: avoid; }
    .report-section { page-break-inside: auto; }
    .summary-report .header h1 { display: none; }
    .summary-report table.summary td { padding: 4px; }
    .note-entry { margin: 7pt 0; white-space: normal; overflow-wrap: break-word; page-break-inside: avoid; }
    .note-entry strong { color: #352451; }
    .section-note { margin: 0 0 6pt; color: #465264; font-size: 9pt; page-break-after: avoid; }
    table.summary { width: 100%; border-collapse: collapse; margin: 5px 0 8px; table-layout: fixed; }
    table.summary td { padding: 6px; background: #f6f4fb; border: 2px solid white; vertical-align: top; word-wrap: break-word; }
    .summary .label { display: block; color: #465264; font-size: 8.5pt; }
    .summary .value { display: block; margin-top: 3pt; font-size: 12pt; font-weight: bold; color: #35205f; }
    .summary .value.period-value { font-size: 10pt; }
    div.summary { padding: 6pt 0; font-size: 10pt; }
    table.data { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 10px; font-size: 9pt; }
    table.data thead { display: table-header-group; }
    /* Keep the initial header with its first row; allow all later rows to paginate. */
    table.data thead tr:last-child { page-break-after: avoid; }
    table.data tbody tr:first-child { page-break-before: avoid; }
    table.data tr { page-break-inside: avoid; }
    table.data th, table.data td { padding: 3pt 4pt; border-bottom: .5pt solid #cbd1da; vertical-align: top; word-wrap: break-word; }
    table.data th { background: #edf0f6; color: #253247; font-size: 9pt; text-align: left; font-weight: bold; }
    table.data tbody tr:nth-child(even) { background: #f8f9fc; }
    table.data tr.total td { background: #eee9f7; border-top: 2px solid #d4c7eb; font-weight: bold; }
    .num { text-align: right; white-space: normal; }
    table.data th.num { text-align: right; }
    .center { text-align: center; }
    .nowrap { white-space: nowrap; }
    .small { font-size: 9pt; }
    .muted { color: #465264; }
    .empty { text-align: center; padding: 9px !important; color: #667085; }
    .page-break { page-break-before: always; }
    .priority-critical, .status-expired { color: #a32935; font-weight: bold; }
    .priority-warning, .status-near { color: #915900; font-weight: bold; }
    .priority-watch { color: #25638d; font-weight: bold; }
    .status-good { color: #1d7054; font-weight: bold; }
    .status-missing { color: #667085; }
</style>
