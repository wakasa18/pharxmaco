<?= $this->extend('layouts/staff') ?>
<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/quarantine.css') ?>?v=20261023">
<link rel="stylesheet" href="<?= base_url('assets/css/damaged-items.css') ?>?v=20261023">
<div class="quarantine-page damaged-page">
    <div class="page-header q-heading"><div><h1>Damaged Items</h1><p class="muted">Returned products marked damaged during a refund, exchange or quarantine inspection.</p></div><a class="btn btn-secondary" href="<?= site_url('admin/quarantine') ?>">Quarantine</a></div>
    <?php if (!$includesInspections): ?><div class="alert warning" role="status">Showing items marked damaged at return. <?= $canViewAllBranches ? 'Import the Quarantine database update to include later inspection outcomes.' : 'Ask an administrator to complete the Quarantine setup to include later inspection outcomes.' ?></div><?php endif; ?>
    <section class="card">
        <div class="card-head"><div><h2>Damaged returns</h2><p class="muted">These units are excluded from saleable stock.</p></div><span class="q-count"><?= number_format(count($records)) ?> <?= count($records) === 1 ? 'record' : 'records' ?> · <?= number_format($totalQuantity) ?> <?= $totalQuantity === 1 ? 'unit' : 'units' ?></span></div>
        <form class="q-filters" method="get" action="<?= site_url('admin/damaged-items') ?>" data-auto-filter="off" aria-label="Filter damaged items">
            <label for="damage-source">Damage source<select class="input" id="damage-source" name="source">
                <?php foreach (['all' => 'All sources'] + \App\Libraries\DamagedItems::SOURCES as $value => $label): ?><option value="<?= esc($value, 'attr') ?>" <?= $source === $value ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach; ?>
            </select></label>
            <?php if ($canViewAllBranches): ?>
            <label for="damage-branch">Branch<select class="input" id="damage-branch" name="branch_id"><option value="0">All branches</option>
                <?php foreach ($branches as $option): ?><option value="<?= (int) $option['id'] ?>" <?= $branch === (int) $option['id'] ? 'selected' : '' ?>><?= esc($option['branch_name']) ?></option><?php endforeach; ?>
            </select></label>
            <?php else: ?><label for="damage-branch">Branch<input class="input" id="damage-branch" value="<?= esc($branches[0]['branch_name'] ?? '', 'attr') ?>" readonly></label><?php endif; ?>
            <div class="q-buttons"><button class="btn btn-primary" type="submit">Apply filters</button><a class="btn btn-secondary" href="<?= site_url('admin/damaged-items') ?>">Reset</a></div>
        </form>
        <p class="q-table-note" id="damaged-table-note">One record per returned product line. Viewing this list does not change stock. Quick search filters this list.</p>
        <div class="table-wrap"><table class="mobile-record-table js-datatable" aria-label="Damaged items" aria-describedby="damaged-table-note" data-record-title="1" data-dt-order="0:desc" data-dt-length="25" data-dt-date-filter="off" data-dt-link-ignore="source,branch_id" data-dt-empty="No damaged items match these filters.">
            <thead><tr><th data-dt-filter="off">Marked damaged</th><th data-dt-filter="off">Product</th><th data-dt-filter="off">Branch</th><th data-dt-filter="off">Quantity</th><th data-dt-filter="off">Source</th><th data-dt-filter="off">Original sale</th><th data-dt-filter="off">Action</th></tr></thead>
            <tbody><?php foreach ($records as $row): ?>
                <tr>
                    <td data-label="Marked damaged" data-order="<?= esc($row['recorded_at'], 'attr') ?>"><?= esc(\App\Libraries\DisplayDate::dateTime($row['recorded_at'])) ?><small>D-<?= (int) $row['id'] ?></small></td>
                    <td data-label="Product"><strong><?= esc($row['product_name_snapshot']) ?></strong><small><?= esc($row['sku'] ?: 'SKU not recorded') ?></small></td>
                    <td data-label="Branch"><?= esc($row['branch_name'] ?: 'Branch unavailable') ?></td>
                    <td data-label="Quantity" data-order="<?= (int) $row['damaged_quantity'] ?>"><?= number_format((int) $row['damaged_quantity']) ?> <?= esc($row['unit'] ?? '') ?></td>
                    <td data-label="Source"><span class="badge"><?= esc(\App\Libraries\DamagedItems::SOURCES[$row['source']]) ?></span><small><?= esc($row['recorded_by'] ?: 'Staff member unavailable') ?></small></td>
                    <td data-label="Original sale"><a href="<?= site_url('cashier/sales/view/' . (int) $row['sale_id']) ?>"><?= esc($row['invoice_no']) ?></a></td>
                    <td data-label="Action"><a class="btn btn-secondary q-review" href="<?= site_url('admin/damaged-items/' . (int) $row['id']) ?>" aria-label="View damaged item D-<?= (int) $row['id'] ?>">View details</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$records): ?><tr><td colspan="7" class="muted q-empty">No damaged items match these filters.</td></tr><?php endif; ?></tbody>
        </table></div>
    </section>
</div>
<?= $this->endSection() ?>
