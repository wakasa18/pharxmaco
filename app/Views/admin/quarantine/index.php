<?= $this->extend('layouts/staff') ?>
<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/quarantine.css') ?>?v=20261023">
<div class="quarantine-page">
    <div class="page-header q-heading"><div><h1>Quarantine</h1><p class="muted"><?= $canInspect ? 'Inspect' : 'View' ?> returned products held outside available stock.</p></div><a class="btn btn-secondary" href="<?= site_url('admin/damaged-items') ?>">Damaged Items</a></div>
    <?php if ($message = session()->getFlashdata('error')): ?><div class="alert danger" role="alert"><?= esc($message) ?></div><?php endif; ?>
    <?php if (!$ready): ?>
        <section class="card"><div class="q-body"><h2>Database update required</h2><?php if ($canInspect): ?><p>Import <strong>2026-10-23-quarantine.sql</strong> in the application database before using this page. Existing returns will appear automatically after the update.</p><?php else: ?><p>Ask an administrator to complete the Quarantine setup before viewing these records.</p><?php endif; ?></div></section>
    <?php else: ?>
    <section class="card">
        <div class="card-head"><div><h2>Returned products</h2><p class="muted">Pending returns need an inspection. Recorded outcomes remain available in the status filter.</p></div><span class="q-count"><?= number_format(count($records)) ?> <?= count($records) === 1 ? 'return' : 'returns' ?></span></div>
        <form class="q-filters" method="get" action="<?= site_url('admin/quarantine') ?>" data-auto-filter="off" aria-label="Filter quarantined returns">
            <label for="q-status">Inspection status<select class="input" id="q-status" name="status">
                <?php foreach (['pending' => 'Pending inspection', 'all' => 'All outcomes'] + \App\Libraries\Quarantine::OUTCOMES as $value => $label): ?><option value="<?= esc($value, 'attr') ?>" <?= $status === $value ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach; ?>
            </select></label>
            <?php if ($canViewAllBranches): ?>
            <label for="q-branch">Branch<select class="input" id="q-branch" name="branch_id"><option value="0">All branches</option>
                <?php foreach ($branches as $option): ?><option value="<?= (int) $option['id'] ?>" <?= $branch === (int) $option['id'] ? 'selected' : '' ?>><?= esc($option['branch_name']) ?></option><?php endforeach; ?>
            </select></label>
            <?php else: ?><label for="q-branch">Branch<input class="input" id="q-branch" value="<?= esc($branches[0]['branch_name'] ?? '', 'attr') ?>" readonly></label><?php endif; ?>
            <div class="q-buttons"><button class="btn btn-primary" type="submit">Apply filters</button><a class="btn btn-secondary" href="<?= site_url('admin/quarantine') ?>">Reset</a></div>
        </form>
        <p class="q-table-note" id="q-table-note">Quantities below are held units, excluded from saleable stock. Quick search filters this list.</p>
        <div class="table-wrap"><table class="mobile-record-table js-datatable" aria-label="Quarantined returns" aria-describedby="q-table-note" data-record-title="1" data-dt-order="0:desc" data-dt-length="25" data-dt-date-filter="off" data-dt-link-ignore="status,branch_id" data-dt-empty="No quarantined returns match these filters.">
            <thead><tr><th data-dt-filter="off">Returned</th><th data-dt-filter="off">Product</th><th data-dt-filter="off">Branch</th><th data-dt-filter="off">Quantity</th><th data-dt-filter="off">Original sale</th><th data-dt-filter="off">Inspection</th><th data-dt-filter="off" data-dt-orderable="false">Action</th></tr></thead>
            <tbody><?php foreach ($records as $row): ?>
                <tr>
                    <td data-label="Returned" data-order="<?= esc($row['created_at'], 'attr') ?>"><?= esc(\App\Libraries\DisplayDate::dateTime($row['created_at'])) ?><small>Q-<?= (int) $row['id'] ?></small></td>
                    <td data-label="Product"><strong><?= esc($row['product_name_snapshot']) ?></strong><small><?= esc($row['sku'] ?: 'SKU not recorded') ?></small></td>
                    <td data-label="Branch"><?= esc($row['branch_name'] ?: 'Branch unavailable') ?></td>
                    <td data-label="Quantity" data-order="<?= (int) $row['quantity_refunded'] ?>"><?= number_format((int) $row['quantity_refunded']) ?> <?= esc($row['unit'] ?? '') ?></td>
                    <td data-label="Original sale"><a href="<?= site_url('cashier/sales/view/' . (int) $row['sale_id']) ?>"><?= esc($row['invoice_no']) ?></a></td>
                    <td data-label="Inspection"><span class="badge <?= !$row['outcome'] ? 'badge-warning' : ($row['outcome'] === 'released' ? 'badge-success' : '') ?>"><?= esc(\App\Libraries\Quarantine::OUTCOMES[$row['outcome']] ?? 'Pending inspection') ?></span><?php if ($row['outcome']): ?><small><?= esc($row['inspector']) ?></small><small><?= esc(\App\Libraries\DisplayDate::dateTime($row['inspected_at'])) ?></small><?php endif; ?></td>
                    <?php $actionLabel = $row['outcome'] ? 'View inspection' : ($canInspect ? 'Inspect return' : 'View details'); ?>
                    <td data-label="Action"><a class="btn btn-secondary q-review" href="<?= site_url('admin/quarantine/' . (int) $row['id']) ?>" aria-label="<?= esc($actionLabel . ' Q-' . $row['id'], 'attr') ?>"><?= esc($actionLabel) ?></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$records): ?><tr><td colspan="7" class="muted q-empty">No quarantined returns match these filters.</td></tr><?php endif; ?></tbody>
        </table></div>
    </section>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
