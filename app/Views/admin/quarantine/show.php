<?= $this->extend('layouts/staff') ?>
<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/quarantine.css') ?>?v=20261023">
<?php
    ['refund' => $refund, 'sale' => $sale, 'item' => $item, 'inventory' => $inventory, 'product' => $product, 'branch' => $branch, 'resolution' => $resolution] = $record;
    $value = static fn ($name) => is_string($input[$name] ?? null) ? $input[$name] : '';
    $quantity = (int) $refund['quantity_refunded'];
?>
<div class="quarantine-page q-detail">
    <div class="page-header q-heading"><div><h1><?= $resolution ? 'Inspection record' : ($canInspect ? 'Inspect quarantined return' : 'Quarantined return') ?></h1><p class="muted">Q-<?= (int) $refund['id'] ?> · <?= esc($branch['branch_name'] ?? 'Branch unavailable') ?></p></div><a class="btn btn-secondary" href="<?= site_url('admin/quarantine') ?>">Back to Quarantine</a></div>
    <?php if ($message = $error ?? session()->getFlashdata('error')): ?><div class="alert danger" role="alert"><?= esc($message) ?></div><?php endif; ?>
    <?php if ($message = session()->getFlashdata('success')): ?><div class="alert success" role="status"><?= esc($message) ?></div><?php endif; ?>
    <div class="q-detail-grid">
    <section class="card"><div class="card-head"><div><h2><?= esc($refund['product_name_snapshot']) ?></h2><p class="muted"><?= esc($product['sku'] ?? 'SKU unavailable') ?></p></div><span class="badge <?= $resolution ? '' : 'badge-warning' ?>"><?= esc(\App\Libraries\Quarantine::OUTCOMES[$resolution['outcome'] ?? ''] ?? 'Pending inspection') ?></span></div>
        <div class="q-body">
            <dl class="q-facts">
                <div><dt>Held quantity</dt><dd><?= number_format($quantity) ?> <?= esc($product['unit'] ?? '') ?></dd></div>
                <div><dt>Returned on</dt><dd><?= esc(\App\Libraries\DisplayDate::dateTime($refund['created_at'])) ?></dd></div>
                <div><dt>Original sale</dt><dd><a href="<?= site_url('cashier/sales/view/' . (int) $sale['id']) ?>"><?= esc($sale['invoice_no']) ?></a></dd></div>
                <div><dt>Expiry recorded at sale</dt><dd><?= esc(\App\Libraries\DisplayDate::date($item['expiration_date_at_sale'] ?? null)) ?></dd></div>
                <div><dt>Return reason</dt><dd><?= esc($refund['reason'] ?: 'No reason recorded') ?></dd></div>
            </dl>
            <?php if (!$resolution): ?>
            <div class="q-stock"><span class="muted">Available stock in this branch</span><strong><?= $inventory ? number_format((int) $inventory['stock']) . ' ' . esc($product['unit'] ?? '') : 'No inventory record' ?></strong><p>These <?= number_format($quantity) ?> held units are outside this balance.</p></div>
            <?php endif; ?>
        </div>
    </section>
    <section class="card"><div class="card-head"><div><h2><?= $resolution ? 'Recorded outcome' : ($canInspect ? 'Inspection outcome' : 'Inspection status') ?></h2><p class="muted"><?= $resolution ? 'Saved by the inspecting administrator.' : ($canInspect ? 'Resolve all units in this returned line together.' : 'Awaiting administrator inspection.') ?></p></div></div>
    <?php if ($resolution): ?>
        <div class="q-body"><dl class="q-facts">
            <div><dt>Outcome</dt><dd><?= esc(\App\Libraries\Quarantine::OUTCOMES[$resolution['outcome']]) ?></dd></div>
            <div><dt>Inspected by</dt><dd><?= esc($resolution['inspector']) ?></dd></div>
            <div><dt>Recorded on</dt><dd><?= esc(\App\Libraries\DisplayDate::dateTime($resolution['inspected_at'])) ?></dd></div>
            <div><dt>Quantity</dt><dd><?= number_format((int) $resolution['quantity']) ?> <?= esc($product['unit'] ?? '') ?></dd></div>
            <div><dt>Inspection note</dt><dd class="q-note-text"><?= esc($resolution['note']) ?></dd></div>
            <div><dt>Stock effect</dt><dd><?php if ($resolution['stock_log_id']): ?><?= number_format((int) $resolution['previous_stock']) ?> → <?= number_format((int) $resolution['new_stock']) ?><small>Stock log #<?= (int) $resolution['stock_log_id'] ?></small><?php else: ?>No change to available stock<?php endif; ?></dd></div>
        </dl><p class="q-notice">The original refund remains recorded as Quarantined. This inspection is retained separately.</p></div>
    <?php elseif ($canInspect): ?>
        <form class="q-body" method="post" action="<?= site_url('admin/quarantine/' . (int) $refund['id']) ?>">
            <?= csrf_field() ?><input type="hidden" name="inspection_token" value="<?= esc($token, 'attr') ?>">
            <?php if ($releaseProblem): ?><div class="q-notice" role="note"><strong>Release to stock is unavailable</strong><p><?= esc($releaseProblem) ?></p></div><?php endif; ?>
            <fieldset class="q-outcomes"><legend>Choose an outcome <span aria-hidden="true">*</span></legend>
                <label><input type="radio" name="outcome" value="released" required <?= $releaseProblem ? 'disabled' : '' ?> <?= !$releaseProblem && $value('outcome') === 'released' ? 'checked' : '' ?>><span><strong>Release to stock</strong><small><?= $inventory && !$releaseProblem ? 'Adds ' . number_format($quantity) . ' units: ' . number_format((int) $inventory['stock']) . ' → ' . number_format((int) $inventory['stock'] + $quantity) : 'Requires matching delivery and valid expiry records.' ?></small></span></label>
                <label><input type="radio" name="outcome" value="damaged" required <?= $value('outcome') === 'damaged' ? 'checked' : '' ?>><span><strong>Mark damaged</strong><small>Keep excluded from saleable stock. No second stock deduction.</small></span></label>
                <label><input type="radio" name="outcome" value="expired" required <?= $value('outcome') === 'expired' ? 'checked' : '' ?>><span><strong>Mark expired</strong><small>Keep excluded from saleable stock. No second stock deduction.</small></span></label>
            </fieldset>
            <label class="q-field" for="q-note">Inspection note <span aria-hidden="true">*</span><textarea class="input" id="q-note" name="note" rows="4" minlength="3" maxlength="500" required placeholder="Record the condition, checks performed and reason for this outcome."><?= esc($value('note')) ?></textarea></label>
            <label class="q-confirm"><input type="checkbox" name="confirmed" value="1" required><span>I inspected all <?= number_format($quantity) ?> units. For a release, I verified the delivery, expiry, packaging and storage condition before approving saleable stock.</span></label>
            <p class="q-notice">Saving records a final outcome for this return. It does not change the customer’s refund or payment.</p>
            <div class="q-buttons"><button class="btn btn-primary" type="submit">Save inspection</button><a class="btn btn-secondary" href="<?= site_url('admin/quarantine') ?>">Cancel</a></div>
        </form>
    <?php else: ?>
        <div class="q-body"><p class="q-notice">These units remain outside available stock until an administrator records an inspection outcome.</p><p class="muted">You can view the return details and any recorded outcome here. Contact an administrator to arrange an inspection.</p></div>
    <?php endif; ?>
    </section>
    </div>
</div>
<?= $this->endSection() ?>
