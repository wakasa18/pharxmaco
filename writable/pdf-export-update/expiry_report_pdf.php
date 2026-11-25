<?php
use App\Libraries\DisplayDate;
$metadata = [['Status', $filterStatusLabel ?? 'All Statuses'], ['As of', DisplayDate::date($today ?? null)], ['Warning window', (int)($nearExpiryDays ?? 30) . ' days']];
$columns = [['label' => 'Product / SKU', 'width' => 32], ['label' => 'Branch', 'width' => 12], ['label' => 'Stock / unit', 'width' => 10, 'num' => true],
    ['label' => 'Expiry date', 'width' => 13], ['label' => 'Days left', 'width' => 7, 'num' => true], ['label' => 'Status / action', 'width' => 14], ['label' => 'Source ref.', 'width' => 12]];
$rows = []; $sources = [];
foreach (($products ?? []) as $index => $p) {
    $expiry = $p['expiration_date'] ?? null; $status = 'No expiry date'; $action = 'Verify expiry'; $days = '-';
    if ($expiry) {
        $days = (string)(new DateTimeImmutable($today))->diff(new DateTimeImmutable($expiry))->format('%r%a');
        if ($expiry < $today) { $status = 'Expired'; $action = 'Remove from sale'; }
        elseif ($expiry <= $nearDate) { $status = 'Near expiry'; $action = 'Review first'; }
        else { $status = 'Good'; $action = 'Normal rotation'; }
    }
    $ref = 'EXP-' . str_pad((string)($index + 1), 3, '0', STR_PAD_LEFT);
    $productId = isset($p['product_id']) ? '#' . (int)$p['product_id'] : 'ID not recorded';
    $rows[] = [($p['product_name'] ?? '-') . "\n" . ($p['sku'] ?: 'SKU not recorded') . ' / ' . $productId,
        $p['branch_name'] ?? '-', (string)($p['stock'] ?? 0) . "\n" . ($p['unit'] ?: 'Not recorded'), DisplayDate::date($expiry ?: '-'), $days, $status . "\n" . $action, $ref];
    $sources[] = ['ref' => $ref, 'product_id' => $p['product_id'] ?? null, 'inventory_id' => $p['id'] ?? null,
        'delivery' => $p['stock_delivery_id'] ?? null, 'manufacturer' => $p['manufacturer'] ?? null, 'supplier' => $p['supplier_name'] ?? null];
}
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Expiry Report</title><?= view('exports/pdf_styles') ?></head><body>
<?= view('exports/pdf_header', array_merge(get_defined_vars(), ['reportTitle' => 'Expiry Report']), ['saveData' => false]) ?>
<table class="summary"><tr>
<?php foreach (['Total records' => 'total', 'Expired' => 'expired', 'Near expiry' => 'near', 'Good' => 'good', 'No expiry date' => 'missing'] as $label => $key): ?>
<td><span class="label"><?= esc($label) ?></span><span class="value"><?= (int)($summary[$key] ?? 0) ?></span></td>
<?php endforeach; ?></tr></table>
<?php if ($rows): ?>
<?= view('exports/pdf_table', ['title' => 'Inventory Expiry Details', 'columns' => $columns, 'rows' => $rows,
    'note' => 'Current branch-product balances as of the report date. Days left = expiry date minus report date. Check source references below before taking action.'], ['saveData' => false]) ?>
<h3 class="section-title">Inventory Source References</h3>
<p class="section-note">Receiving references identify internal stock provenance, not manufacturer lots. Manufacturer batch/lot numbers are not recorded in the current inventory data. Supplier and manufacturer are current catalog values.</p>
<?php foreach ($sources as $source): ?>
<p class="note-entry"><strong><?= esc($source['ref']) ?></strong> | Product <?= $source['product_id'] ? '#' . (int)$source['product_id'] : 'ID not recorded' ?> | Branch inventory <?= $source['inventory_id'] ? '#' . (int)$source['inventory_id'] : 'ID not recorded' ?><br>
Receiving ref: <?= esc($source['delivery'] ?: 'Not recorded') ?> | Manufacturer lot: Not recorded<br>
Manufacturer: <?= esc($source['manufacturer'] ?: 'Not recorded') ?> | Supplier: <?= esc($source['supplier'] ?: 'Not recorded') ?></p>
<?php endforeach; ?>
<?php else: ?><p class="notice">No expiry records matched the selected filters.</p><?php endif; ?>
</body></html>
