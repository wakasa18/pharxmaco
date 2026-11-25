<?= $this->extend('layouts/staff') ?>
<?= $this->section('content') ?>
<?php
    $labels = \App\Libraries\ExpiryStockResolution::ACTIONS;
    $input ??= [];
    $getValue = static fn ($key, $fallback = '') => is_scalar($input[$key] ?? null) ? $input[$key] : $fallback;
    $active = $row['status'] === 'active' && $row['product_status'] === 'active' && $row['branch_status'] === 'active' && $row['deleted_at'] === null && empty($row['branch_deleted_at']) && empty($row['is_deleted']) && empty($row['branch_is_deleted']) && empty($row['is_permanently_deleted']) && empty($row['branch_is_permanently_deleted']);
    $heading = $labels[$action] ?? 'Expiry resolution history';
    $batchQty = (int) ($row['batch_quantity'] ?? 0);
    $batchNumbers = \App\Libraries\StockBatches::numbers(db_connect(), array_merge([(int) ($row['batch']['id'] ?? 0)], array_column($row['batches'] ?? [], 'id')));
    $batchLabel = !empty($row['batch']) ? \App\Libraries\StockBatches::label($row['batch']['batch_no'] ?? null, $batchNumbers[(int) $row['batch']['id']] ?? null) : 'No batch with stock';
    $batchQuery = !empty($row['batch_id']) ? '&batch=' . (int) $row['batch_id'] : '';
?>
<style>
.expiry-action-page{max-width:1050px;margin:0 auto;min-width:0}
.expiry-action-page .card{margin-bottom:20px}
.expiry-action-page .expiry-body{padding:20px}
.expiry-action-page .expiry-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
.expiry-action-page .expiry-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin:18px 0}
.expiry-action-page .expiry-summary>div{background:var(--surface);border:1px solid var(--line);padding:16px;border-radius:12px;min-width:0;overflow-wrap:anywhere}
.expiry-action-page .expiry-summary strong{display:block;margin-top:5px}
.expiry-action-page .expiry-actions{display:flex;flex-wrap:wrap;gap:10px;margin:18px 0}
.expiry-action-page .expiry-note{padding:14px;border:1px solid var(--line);border-radius:10px;background:var(--surface-2);line-height:1.6;margin-bottom:20px}
.expiry-action-page .expiry-confirm{display:flex;gap:10px;align-items:flex-start;padding:16px 0;line-height:1.6}
.expiry-action-page .expiry-confirm input{margin-top:6px;flex:none}
.expiry-action-page .input{min-height:44px;min-width:0;width:100%}
.expiry-action-page td{overflow-wrap:anywhere}
@media(max-width:640px){.expiry-action-page .expiry-grid,.expiry-action-page .expiry-summary{grid-template-columns:1fr}.expiry-action-page .expiry-body{padding:14px}.expiry-action-page .expiry-actions .btn{flex:1;min-height:44px;white-space:normal}}
</style>
<div class="expiry-action-page">
    <div class="page-header"><h1><?= esc($heading) ?></h1><p><?= esc($row['product_name']) ?> · <?= esc($row['branch_name']) ?></p></div>
    <?php $message = $error ?? session()->getFlashdata('error'); ?>
    <?php if ($message): ?><div class="alert danger" role="alert"><?= esc($message) ?></div><?php endif; ?>
    <?php if ($message = session()->getFlashdata('success')): ?><div class="alert success" role="status"><?= esc($message) ?></div><?php endif; ?>
    <div class="expiry-summary">
        <div><span class="muted">Batch / lot</span><strong><?= esc($batchLabel) ?></strong></div>
        <div><span class="muted">Stock in this batch</span><strong><?= number_format($batchQty) ?> <?= esc($row['unit'] ?? '') ?></strong><span class="muted"><?= number_format((int) $row['stock']) ?> in all batches</span></div>
        <div><span class="muted">Batch expiration date</span><strong><?= esc(\App\Libraries\DisplayDate::date($row['batch_expiration_date'] ?? null)) ?></strong></div>
        <div><span class="muted">Inventory status in this branch</span><strong><?= esc(ucfirst($row['status'])) ?></strong></div>
    </div>
    <?php if ($action !== 'history' && $active): ?>
    <section class="card"><div class="card-head"><div><h2><?= esc($heading) ?></h2><p>This action applies to <?= esc($row['branch_name']) ?> only.</p></div></div>
    <form class="expiry-body" method="post" action="<?= site_url('admin/expiry-report/item/' . (int) $row['id']) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="resolution_action" value="<?= esc($action, 'attr') ?>">
        <input type="hidden" name="resolution_token" value="<?= esc($token, 'attr') ?>">
        <input type="hidden" name="batch_id" value="<?= (int) ($row['batch_id'] ?? 0) ?>">
        <?php if ($action === 'replace'): ?>
            <p class="expiry-note">This will remove all <strong><?= $batchQty ?> units of this batch (<?= esc($batchLabel) ?>)</strong> before receiving the new stock as its own batch. Other batches of this product are not changed. The old expiry and removal are kept in history. Confirm that the old stock has been separated from the new delivery. Current selling and cost prices stay the same.</p>
        <?php elseif ($action === 'remove'): ?>
            <p class="expiry-note">Record the quantity physically disposed of or returned to the supplier from this batch. Other batches and the remaining units of this batch keep their expiration dates. This records inventory movement only; supplier payments or credits are not processed here.</p>
        <?php else: ?>
            <p class="expiry-note">This stops sales of this product in <strong><?= esc($row['branch_name']) ?></strong>. Its <?= (int) $row['stock'] ?> units remain recorded, and other branches remain available. Use Dispose / Return to supplier if stock has physically left the branch. An administrator can reactivate this branch inventory through Edit Product.</p>
        <?php endif; ?>
        <div class="expiry-grid">
            <?php if ($action !== 'deactivate'): ?>
            <div class="field"><label class="label" for="expiryOutcome">What happened to the old stock?</label>
                <select class="input" id="expiryOutcome" name="outcome" required><option value="">Select disposition</option><option value="disposed" <?= $getValue('outcome') === 'disposed' ? 'selected' : '' ?>>Disposed</option><option value="supplier_return" <?= $getValue('outcome') === 'supplier_return' ? 'selected' : '' ?>>Returned to supplier</option></select>
            </div>
            <?php endif; ?>
            <?php if ($action === 'remove'): ?>
            <div class="field"><label class="label" for="expiryQuantity">Quantity removed</label><input class="input" id="expiryQuantity" type="number" name="quantity" min="1" max="<?= min(1000000, $batchQty) ?>" step="1" value="<?= esc($getValue('quantity', $batchQty), 'attr') ?>" required></div>
            <?php elseif ($action === 'replace'): ?>
            <div class="field"><label class="label" for="expiryNewQuantity">New stock</label><input class="input" id="expiryNewQuantity" type="number" name="new_quantity" min="1" max="1000000" step="1" value="<?= esc($getValue('new_quantity', $batchQty ?: ''), 'attr') ?>" required></div>
            <div class="field"><label class="label" for="expiryNewLot">New batch / lot number <span class="muted">(optional)</span></label><input class="input" id="expiryNewLot" type="text" name="new_batch_no" maxlength="60" value="<?= esc($getValue('new_batch_no'), 'attr') ?>"></div>
            <div class="field"><label class="label" for="expiryNewDate">New expiration date</label><?= view('partials/expiry_date_field', ['expiryFieldId' => 'expiryNewDate', 'expiryFieldName' => 'new_expiry', 'expiryValue' => $getValue('new_expiry'), 'expiryOriginal' => '', 'expiryRequired' => true], ['saveData' => false]) ?></div>
            <?php endif; ?>
            <div class="field"><label class="label" for="expiryReason">Reason / reference</label><textarea class="input" id="expiryReason" name="reason" minlength="3" maxlength="100" rows="3" required><?= esc($getValue('reason')) ?></textarea><p class="muted">Include a supplier reference when applicable. Maximum 100 characters.</p></div>
        </div>
        <label class="expiry-confirm"><input type="checkbox" name="confirmed" value="1" required><span>I checked the product, branch, quantities and expiry details and confirm this action.</span></label>
        <div class="expiry-actions"><a class="btn secondary" href="<?= site_url('admin/expiry-report/item/' . (int) $row['id'] . ($batchQuery ? '?' . ltrim($batchQuery, '&') : '')) ?>">Cancel</a><button type="submit" class="btn <?= $action === 'deactivate' ? 'danger' : 'btn-primary' ?>"><?= $action === 'replace' ? 'Confirm replacement' : ($action === 'remove' ? 'Confirm removal' : 'Deactivate in this branch') ?></button></div>
    </form></section>
    <?php elseif ($action !== 'history'): ?>
        <div class="expiry-note">This inventory is inactive or the product is in trash. Its history remains available below.</div>
    <?php endif; ?>
    <?php if ($action === 'history' && $active): ?>
    <div class="expiry-actions">
        <a class="btn btn-primary" href="<?= site_url('admin/expiry-report/item/' . (int) $row['id'] . '?action=replace' . $batchQuery) ?>">Replace with new stock</a>
        <?php if ($batchQty > 0): ?><a class="btn secondary" href="<?= site_url('admin/expiry-report/item/' . (int) $row['id'] . '?action=remove' . $batchQuery) ?>">Dispose / Return to supplier</a><?php endif; ?>
        <?php if (session('role') === 'admin'): ?><a class="btn danger" href="<?= site_url('admin/expiry-report/item/' . (int) $row['id'] . '?action=deactivate') ?>">Deactivate in this branch</a><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($row['batches'])): ?>
    <section class="card"><div class="card-head"><div><h2>Batches in this branch</h2><p>Every batch with stock, nearest expiry first. Choose a batch to act on it.</p></div></div>
        <div class="table-wrap"><table class="mobile-record-table"><thead><tr><th>Batch / lot</th><th>Expiration date</th><th>Stock</th><th>Received</th><th>Actions</th></tr></thead><tbody>
        <?php $todayDate = date('Y-m-d'); foreach ($row['batches'] as $b): $expired = !empty($b['expiration_date']) && $b['expiration_date'] <= $todayDate; ?>
            <tr<?= (int) $b['id'] === (int) ($row['batch_id'] ?? 0) ? ' style="font-weight:600"' : '' ?>><td><?= esc(\App\Libraries\StockBatches::label($b['batch_no'] ?? null, $batchNumbers[(int) $b['id']] ?? null)) ?></td>
            <td><?= esc(\App\Libraries\DisplayDate::date($b['expiration_date'])) ?><?= $expired ? ' <span class="badge badge-danger">Expired</span>' : '' ?></td>
            <td><?= number_format((int) $b['quantity_remaining']) ?></td><td><?= esc(\App\Libraries\DisplayDate::date($b['received_at'] ?? null)) ?></td>
            <td><div class="expiry-row-actions"><a class="btn secondary" href="<?= site_url('admin/expiry-report/item/' . (int) $row['id'] . '?action=remove&batch=' . (int) $b['id']) ?>">Dispose / Return</a>
            <a class="btn secondary" href="<?= site_url('admin/expiry-report/item/' . (int) $row['id'] . '?action=replace&batch=' . (int) $b['id']) ?>">Replace</a></div></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </section>
    <?php endif; ?>
    <section class="card"><div class="card-head"><div><h2>Resolution history</h2><p>Latest 50 movements for this product in this branch. A replacement records old stock out and new stock in separately.</p></div></div>
        <div class="table-wrap"><table class="mobile-record-table"><thead><tr><th>Action and details</th><th>Quantity</th><th>Stock before → after</th><th>Staff</th><th>Recorded</th></tr></thead><tbody>
        <?php foreach ($history as $entry): ?>
            <tr><td><?= esc(preg_replace('/\s*\[ER:[a-f0-9]{32}\]$/', '', $entry['remarks'])) ?></td><td><?= (int) $entry['quantity'] ?></td><td><?= (int) $entry['previous_stock'] ?> → <?= (int) $entry['new_stock'] ?></td><td><?= esc($entry['full_name'] ?? 'Unknown staff') ?></td><td><?= esc(\App\Libraries\DisplayDate::dateTime($entry['created_at'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$history): ?><tr><td colspan="5">No expiry-stock resolutions recorded for this product yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
    <div class="expiry-actions"><a class="btn secondary" href="<?= site_url('admin/expiry-report?branch_id=' . (int) $row['branch_id']) ?>">Back to expiry report</a><a class="btn secondary" href="<?= site_url('stock-logs?branch_id=' . (int) $row['branch_id'] . '&search=' . urlencode('Expiry resolution:')) ?>">All expiry stock logs</a></div>
</div>
<?= $this->endSection() ?>
