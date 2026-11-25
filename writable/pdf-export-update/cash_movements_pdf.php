<?php
$movement = $cashMovements ?? ['rows' => [], 'totals' => [], 'returns' => []];
$columns = [['label' => 'Date', 'width' => 20], ['label' => 'Payment method', 'width' => 23], ['label' => 'Receipts', 'width' => 19, 'num' => true], ['label' => 'Refund payouts', 'width' => 19, 'num' => true], ['label' => 'Net movement', 'width' => 19, 'num' => true]];
$sourceRows = $movement['rows'] ?? [];
if (!empty($compact)) {
    $groups = [];
    foreach ($sourceRows as $row) {
        $method = $row['method'];
        $groups[$method] ??= ['method' => $method, 'receipts' => 0, 'refunds' => 0, 'net' => 0];
        foreach (['receipts', 'refunds', 'net'] as $key) $groups[$method][$key] += $row[$key];
    }
    $sourceRows = array_values($groups);
    $columns = [['label' => 'Payment method', 'width' => 40], ['label' => 'Receipts', 'width' => 20, 'num' => true], ['label' => 'Refund payouts', 'width' => 20, 'num' => true], ['label' => 'Net movement', 'width' => 20, 'num' => true]];
}
$rows = [];
foreach ($sourceRows as $row) {
    $cells = empty($compact) ? [\App\Libraries\DisplayDate::date($row['date'])] : [];
    $cells[] = $row['method'] === 'unknown' ? 'Unknown (legacy)' : strtoupper($row['method']);
    foreach (['receipts', 'refunds', 'net'] as $key) $cells[] = number_format((float)$row[$key], 2);
    $rows[] = $cells;
}
if ($rows) {
    $total = empty($compact) ? ['', 'TOTAL'] : ['TOTAL'];
    foreach (['receipts', 'refunds', 'net'] as $key) $total[] = number_format((float)($movement['totals'][$key] ?? 0), 2);
    $rows[] = $total;
    echo view('exports/pdf_table', ['title' => 'Receipts and Refund Payouts', 'columns' => $columns, 'rows' => $rows,
        'note' => 'PHP. Receipts use the sale date; payouts use the refund date, including older invoices. Corrected values exclude change and exchange credit.'], ['saveData' => false]);
} else { echo '<p class="section-note">No receipts or refund payouts in this period.</p>'; }
?>
<?php if ($rows): ?><p class="section-note"><strong>Recorded cash movement:</strong> PHP <?= number_format((float)($movement['totals']['cash_net'] ?? 0), 2) ?>.
<?php if (($movement['totals']['unknown_refunds'] ?? 0) > 0): ?>Refunds without a recorded payout method: PHP <?= number_format((float)$movement['totals']['unknown_refunds'], 2) ?>. Review before reconciling the drawer.<?php endif; ?></p><?php endif; ?>
<?php if (!empty($movement['returns'])): ?><p class="section-note"><strong>Returned units:</strong>
<?php foreach ($movement['returns'] as $row): ?><?= esc(ucfirst($row['return_condition'])) ?>: <?= (int)$row['quantity'] ?> &nbsp; <?php endforeach; ?>
Only resellable returns increase available stock.</p><?php endif; ?>
