<?= $this->extend('layouts/staff') ?>
<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/quarantine.css') ?>?v=20261023">
<link rel="stylesheet" href="<?= base_url('assets/css/damaged-items.css') ?>?v=20261023">
<div class="quarantine-page damaged-page q-detail">
    <div class="page-header q-heading"><div><h1>Damaged item details</h1><p class="muted">D-<?= (int) $record['id'] ?> · <?= esc($record['branch_name'] ?: 'Branch unavailable') ?></p></div><a class="btn btn-secondary" href="<?= site_url('admin/damaged-items') ?>">Back to Damaged Items</a></div>
    <div class="q-detail-grid">
    <section class="card"><div class="card-head"><div><h2><?= esc($record['product_name_snapshot']) ?></h2><p class="muted"><?= esc($record['sku'] ?: 'SKU not recorded') ?></p></div><span class="badge badge-danger">Damaged</span></div>
        <div class="q-body"><dl class="q-facts">
            <div><dt>Damaged quantity</dt><dd><?= number_format((int) $record['damaged_quantity']) ?> <?= esc($record['unit'] ?? '') ?></dd></div>
            <div><dt>Branch</dt><dd><?= esc($record['branch_name'] ?: 'Branch unavailable') ?></dd></div>
            <div><dt>Marked damaged on</dt><dd><?= esc(\App\Libraries\DisplayDate::dateTime($record['recorded_at'])) ?></dd></div>
            <div><dt>Recorded by</dt><dd><?= esc($record['recorded_by'] ?: 'Staff member unavailable') ?></dd></div>
            <div><dt>Damage source</dt><dd><?= esc(\App\Libraries\DamagedItems::SOURCES[$record['source']]) ?></dd></div>
            <div><dt><?= $record['source'] === 'quarantine' ? 'Inspection note' : 'Return reason' ?></dt><dd class="q-note-text"><?= esc($record['damage_note'] ?: 'No note recorded') ?></dd></div>
        </dl></div>
    </section>
    <section class="card"><div class="card-head"><div><h2>Source records</h2><p class="muted">Trace this damaged return to its original transaction.</p></div></div>
        <div class="q-body"><dl class="q-facts">
            <div><dt>Original sale</dt><dd><a href="<?= site_url('cashier/sales/view/' . (int) $record['sale_id']) ?>"><?= esc($record['invoice_no']) ?></a></dd></div>
            <div><dt>Returned on</dt><dd><?= esc(\App\Libraries\DisplayDate::dateTime($record['created_at'])) ?></dd></div>
            <div><dt>Return processed by</dt><dd><?= esc($record['returned_by_name'] ?: 'Staff member unavailable') ?></dd></div>
            <div><dt>Original return condition</dt><dd><?= esc(ucfirst($record['return_condition'])) ?></dd></div>
            <?php if ($record['source'] === 'quarantine'): ?><div><dt>Original return reason</dt><dd class="q-note-text"><?= esc($record['reason'] ?: 'No reason recorded') ?></dd></div><?php endif; ?>
            <?php if ($record['exchange']): ?><div><dt>Exchange receipt</dt><dd><a href="<?= site_url('cashier/sales/receipt/' . (int) $record['exchange']['id']) ?>"><?= esc($record['exchange']['invoice_no']) ?></a></dd></div><?php endif; ?>
        </dl>
        <p class="q-notice damage-stock-note"><strong>Excluded from saleable stock</strong><br>These returned units were not added to available stock. This record does not make another stock deduction or confirm physical disposal.</p>
        <div class="q-buttons">
            <?php if ($slipUrl): ?><a class="btn btn-secondary" href="<?= esc($slipUrl, 'attr') ?>"><?= $record['exchange'] ? 'View exchange return slip' : 'View refund slip' ?></a><?php endif; ?>
            <?php if ($record['source'] === 'quarantine'): ?><a class="btn btn-secondary" href="<?= site_url('admin/quarantine/' . (int) $record['id']) ?>">View quarantine inspection</a><?php endif; ?>
        </div>
        </div>
    </section>
    </div>
</div>
<?= $this->endSection() ?>
