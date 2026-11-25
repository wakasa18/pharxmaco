<?php
use App\Libraries\DisplayDate;
$metadata = [['Status / window', ($filterStatusLabel ?? 'All Statuses') . ' / ' . (int)($nearExpiryDays ?? 30) . ' days'], ['As of', DisplayDate::date($today ?? null)]];
$columns = [['label' => 'Product / ID', 'width' => 19], ['label' => 'SKU', 'width' => 9], ['label' => 'Manufacturer', 'width' => 10],
    ['label' => 'Supplier', 'width' => 10], ['label' => 'Branch', 'width' => 9], ['label' => 'Batch / lot', 'width' => 9], ['label' => 'Stock / unit', 'width' => 7, 'num' => true],
    ['label' => 'Expiry date', 'width' => 9], ['label' => 'Days left', 'width' => 5, 'num' => true], ['label' => 'Status', 'width' => 7], ['label' => 'Source ref.', 'width' => 6]];
$rows = []; $sources = [];
foreach (($products ?? []) as $p) {
    $expiry = $p['expiration_date'] ?? null; $status = 'No expiry date'; $days = '-';
    if ($expiry) {
        $days = (string)(new DateTimeImmutable($today))->diff(new DateTimeImmutable($expiry))->format('%r%a');
        if ($expiry <= $today) $status = 'Expired';
        elseif ($expiry <= $nearDate) $status = 'Near expiry';
        else $status = 'Good';
    }
    $productId = isset($p['product_id']) ? '#' . (int)$p['product_id'] : 'ID not recorded';
    $sourceKey = (string)($p['batch_id'] ?? $p['id'] ?? '') . ':' . (string)($p['stock_delivery_id'] ?? '') . ':' . (string)($p['branch_id'] ?? '');
    if (!isset($sources[$sourceKey])) $sources[$sourceKey] = 'SRC-' . str_pad((string)(count($sources) + 1), 3, '0', STR_PAD_LEFT)
        . ' | Inventory ' . (isset($p['id']) ? '#' . (int)$p['id'] : 'ID not recorded') . "\n" . (($p['stock_delivery_id'] ?? '') ?: 'Receiving ref: Not recorded');
    $ref = strtok($sources[$sourceKey], ' ');
    $rows[] = [($p['product_name'] ?? '-') . "\n" . $productId, ($p['sku'] ?? '') ?: 'Not recorded',
        ($p['manufacturer'] ?? '') ?: 'Not recorded', ($p['supplier_name'] ?? '') ?: 'Not recorded', $p['branch_name'] ?? '-',
        \App\Libraries\StockBatches::label($p['batch_no'] ?? null, isset($p['batch_number']) ? (int) $p['batch_number'] : null),
        (string)($p['stock'] ?? 0) . "\n" . (($p['unit'] ?? '') ?: 'Not recorded'), $expiry ?: '-', $days, $status, $ref];
}
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Expiry Report</title><?= view('exports/pdf_styles', [], ['saveData' => false]) ?></head><body>
<?= view('exports/pdf_header', \App\Libraries\ReportPdf::header(get_defined_vars(), 'Expiry Report', $metadata), ['saveData' => false]) ?>
<table class="summary"><tr>
<?php foreach (['Total records' => 'total', 'Expired' => 'expired', 'Near expiry' => 'near', 'Good' => 'good', 'No expiry date' => 'missing'] as $label => $key): ?>
<td><span class="label"><?= esc($label) ?></span><span class="value"><?= (int)($summary[$key] ?? 0) ?></span></td>
<?php endforeach; ?></tr></table>
<?php if ($rows): ?>
<?= view('exports/pdf_table', ['title' => 'Inventory Expiry Details', 'columns' => $columns, 'rows' => $rows,
    'note' => 'Current batch balances. Dates: YYYY-MM-DD. Days left = expiry date minus report date. Expired on or before the report date: remove from sale; near expiry: review first; missing date: verify expiry. Supplier and manufacturer are current catalog values.'], ['saveData' => false]) ?>
<?php
$sourceRows = array_map(static fn($pair) => array_pad($pair, 2, ''), array_chunk(array_values($sources), 2));
echo view('exports/pdf_table', ['title' => 'Receiving References', 'columns' => [['label' => 'Source / inventory / internal receiving ref.', 'width' => 50], ['label' => 'Source / inventory / internal receiving ref.', 'width' => 50]],
    'rows' => $sourceRows, 'note' => 'Each row is one batch with stock on hand. Batch / lot shows the lot number entered when the batch was received; Batch N (numbered per product, oldest first) is shown when none was entered. Internal receiving references identify the batch delivery. Source labels apply only to this report.'], ['saveData' => false]);
?>
<?php else: ?><p class="notice">No expiry records matched the selected filters.</p><?php endif; ?>
</body></html>
