<?php
use App\Libraries\DisplayDate;
use App\Libraries\ReportPdf;
$metadata = [['Type', $actionTypeLabel ?? 'All Types'], ['Date range', ($dateFrom ? DisplayDate::date($dateFrom) : 'All dates') . ' to ' . ($dateTo ? DisplayDate::date($dateTo) : 'All dates')], ['Search', $search ?: 'None']];
$columns = [['label' => 'Log / time (PHT)', 'width' => 13], ['label' => 'Product / SKU / ID', 'width' => 23], ['label' => 'Branch / user', 'width' => 16],
    ['label' => 'Movement / source', 'width' => 17], ['label' => 'Change / unit', 'width' => 9, 'num' => true], ['label' => 'Before / after', 'width' => 8, 'num' => true], ['label' => 'Note', 'width' => 14]];
$rows = []; $notes = [];
foreach (($logs ?? []) as $log) {
    $remarks = (string)($log['remarks'] ?? '');
    $short = ReportPdf::text($remarks, 65);
    $noteRef = 'LOG-' . (int)($log['id'] ?? 0);
    $hasNote = $short !== trim($remarks) || str_contains($remarks, "\n");
    if ($hasNote) $notes[] = ['ref' => $noteRef, 'text' => $remarks];
    $delta = (int)($log['new_stock'] ?? 0) - (int)($log['previous_stock'] ?? 0);
    $time = !empty($log['created_at']) ? (new DateTimeImmutable($log['created_at']))->format("Y-m-d\nH:i") : 'Time not recorded';
    $rows[] = ['#' . (string)($log['id'] ?? '-') . "\n" . $time, ($log['product_name'] ?? 'Catalog name unavailable') . "\n" . ($log['sku'] ?? 'SKU not recorded') . ' / #' . (int)($log['product_id'] ?? 0),
        ($log['branch_name'] ?? '-') . "\n" . ($log['full_name'] ?? '-'),
        ucwords(str_replace('_', ' ', $log['action_type'] ?? '-')) . "\n" . ReportPdf::reference($log),
        ($delta > 0 ? '+' : '') . $delta . "\n" . (($log['unit'] ?? '') ?: 'Not recorded'),
        (string)($log['previous_stock'] ?? 0) . ' -> ' . (string)($log['new_stock'] ?? 0),
        $hasNote ? 'See ' . $noteRef : $short];
}
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Stock Logs Report</title><?= view('exports/pdf_styles', [], ['saveData' => false]) ?></head><body>
<?= view('exports/pdf_header', ReportPdf::header(get_defined_vars(), 'Stock Logs Report', $metadata), ['saveData' => false]) ?>
<p><strong>Total records:</strong> <?= count($logs ?? []) ?></p>
<?php if ($rows): ?>
<?= view('exports/pdf_table', ['title' => 'Stock Movements', 'columns' => $columns, 'rows' => $rows,
    'note' => 'Change = after - before; positive adds stock, negative removes stock. Product IDs are stable; names, SKUs and units are current catalog values. Source is the recorded invoice or stock log reference.'], ['saveData' => false]) ?>
<?php if ($notes): ?>
<h3 class="section-title">Complete Movement Notes</h3>
<p class="section-note">Complete text for notes shortened in the movement table. References match the original stock-log ID.</p>
<?php foreach ($notes as $note): ?><div class="note-entry"><strong><?= esc($note['ref']) ?></strong><br><?= nl2br(esc($note['text'])) ?></div><?php endforeach; ?>
<?php endif; ?>
<?php else: ?><p class="notice">No stock logs matched the selected filters.</p><?php endif; ?>
</body></html>
