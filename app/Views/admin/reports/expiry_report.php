<?= $this->extend('layouts/staff') ?>
<?= $this->section('content') ?>
<style>
.expiry-row-actions{display:flex;flex-wrap:wrap;gap:8px;min-width:210px}
.expiry-row-actions .btn{white-space:normal;text-align:left}
@media(max-width:700px){.expiry-row-actions{min-width:0}.expiry-row-actions .btn{flex:1 1 100%}}
</style>

<div class="page-header" style="margin-bottom:20px;">
    <h1 style="margin:0;">Expiry Report</h1>
    <p class="muted" style="margin:6px 0 0;">Monitor expired and near-expiry products by branch. Items are expired from the start of their expiration date.</p>
</div>

<?php if ($expiryError = session()->getFlashdata('error')): ?><div class="alert danger" role="alert"><?= esc($expiryError) ?></div><?php endif; ?>

<?php if (!empty($filterNotices)): ?>
    <div style="margin-bottom:16px; padding:12px 14px; border:1px solid rgba(217,119,6,.28); border-radius:10px; background:var(--warning-bg); font-size:12.5px;">
        <?php foreach ($filterNotices as $notice): ?>
            <div><?= esc($notice) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-head">
        <div>
            <h2 style="margin:0;">Expiry Records</h2>
            <p class="muted" style="margin:6px 0 0;">Filter and export expiry status of products across branches.</p>
        </div>
        <a class="btn btn-secondary" href="<?= site_url('stock-logs?search=' . urlencode('Expiry resolution:')) ?>">Resolution history</a>
    </div>

    <div class="dt-export-bar">
        <a href="<?= site_url('admin/expiry-report/export-excel?branch_id=' . urlencode($filterBranch ?? '') . '&status=' . urlencode($filterStatus ?? 'all')) ?>"
                class="btn btn-secondary">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Excel CSV
            </a>
        <a href="<?= site_url('admin/expiry-report/export-pdf?branch_id=' . urlencode($filterBranch ?? '') . '&status=' . urlencode($filterStatus ?? 'all')) ?>"
                class="btn btn-secondary">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
                </svg>
                PDF
            </a>
    </div>


    <?php if (!empty($tableTruncated)): ?>
        <p class="dt-cap-note" role="status">This list is very long, so only the first <?= number_format(3000) ?> records are loaded here.</p>
    <?php endif; ?>
    <div class="table-wrap">
        <table class="mobile-record-table js-datatable">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Manufacturer</th>
                    <th>Supplier</th>
                    <th>Branch</th>
                    <th>Batch / Lot</th>
                    <th>Stock</th>
                    <th>Expiration Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $p): ?>
                        <?php
                        $expiry = $p['expiration_date'] ?? null;
                        $isExpired = $expiry && $expiry <= $today;
                        $isNear = $expiry && !$isExpired && $expiry <= $nearDate;
                        ?>
                        <tr>
                            <td><strong><?= esc($p['product_name'] ?? '-') ?></strong></td>
                            <td><?= esc($p['sku'] ?? '-') ?></td>
                            <td><?= esc($p['manufacturer'] ?: '—') ?></td>
                            <td><?= esc($p['supplier_name'] ?: '—') ?></td>
                            <td><?= esc($p['branch_name'] ?? '-') ?></td>
                            <td><?= esc(\App\Libraries\StockBatches::label($p['batch_no'] ?? null, isset($p['batch_number']) ? (int) $p['batch_number'] : null)) ?></td>
                            <td><?= esc($p['stock'] ?? 0) ?></td>
                            <td><?= esc(\App\Libraries\DisplayDate::date($expiry ?? '-')) ?></td>
                            <td>
                                <?php if (empty($expiry)): ?>
                                    <span class="badge badge-neutral">No Expiry Date</span>
                                <?php elseif ($isExpired): ?>
                                    <span class="badge badge-danger">Expired</span>
                                <?php elseif ($isNear): ?>
                                    <span class="badge badge-warning">Near Expiry</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Good</span>
                                <?php endif; ?>
                            </td>
                            <td><div class="expiry-row-actions">
                                <a class="btn btn-secondary" href="<?= site_url('admin/expiry-report/item/' . (int) $p['id'] . '?action=replace&batch=' . (int) $p['batch_id']) ?>">Replace stock</a>
                                <?php if ((int) $p['stock'] > 0): ?><a class="btn btn-secondary" href="<?= site_url('admin/expiry-report/item/' . (int) $p['id'] . '?action=remove&batch=' . (int) $p['batch_id']) ?>">Dispose / Return</a><?php endif; ?>
                                <?php if (session('role') === 'admin'): ?><a class="btn danger" href="<?= site_url('admin/expiry-report/item/' . (int) $p['id'] . '?action=deactivate') ?>">Deactivate in branch</a><?php endif; ?>
                                <a class="btn btn-secondary" href="<?= site_url('admin/expiry-report/item/' . (int) $p['id'] . '?batch=' . (int) $p['batch_id']) ?>">View history</a>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="muted" style="text-align:center; padding:20px;">
                            No expiry records found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    </div>

<?= $this->endSection() ?>
