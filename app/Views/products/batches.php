<?= $this->extend('layouts/staff') ?>
<?= $this->section('content') ?>
<?php
    $today = date('Y-m-d');
    $nearDate = date('Y-m-d', strtotime('+30 days'));
    $total = 0;
    foreach ($batches as $b) $total += (int) $b['quantity_remaining'];
?>
<style>
.batches-page{max-width:1100px;margin:0 auto;min-width:0}
.batches-page .card{margin-bottom:20px}
.batches-page .batch-actions{display:flex;flex-wrap:wrap;gap:10px;margin:18px 0}
.batches-page .batch-edit{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) minmax(0,1.4fr) auto;gap:8px;align-items:end}
.batches-page .batch-edit .input{min-height:40px;width:100%;min-width:0}
.batches-page td{overflow-wrap:anywhere}
@media(max-width:760px){.batches-page .batch-edit{grid-template-columns:1fr}}
</style>
<div class="batches-page">
    <div class="page-header"><h1>Batches</h1><p><?= esc($row['product_name']) ?> · <?= esc($row['branch_name']) ?></p></div>
    <?php if ($message = session()->getFlashdata('error')): ?><div class="alert danger" role="alert"><?= esc($message) ?></div><?php endif; ?>
    <?php if ($message = session()->getFlashdata('success')): ?><div class="alert success" role="status"><?= esc($message) ?></div><?php endif; ?>

    <section class="card">
        <div class="card-head"><div>
            <h2><?= $showAll ? 'All batches' : 'Batches with stock' ?></h2>
            <p>Each delivery is tracked as its own batch with its own expiry date. Sales use the batch that expires first, and expired batches are never sold.</p>
        </div></div>
        <div class="table-wrap"><table class="mobile-record-table">
            <thead><tr><th>Batch / lot</th><th>Expiration date</th><th>Remaining</th><th>Received</th><th>Received on</th><th>Supplier</th><?php if ($canCorrect): ?><th>Correct details</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($batches as $b):
                $expiry = $b['expiration_date'];
                $expired = $expiry !== null && $expiry <= $today;
                $near = $expiry !== null && !$expired && $expiry <= $nearDate;
            ?>
                <tr>
                    <td data-label="Batch / lot"><?= esc(\App\Libraries\StockBatches::label($b['batch_no'] ?? null, $batchNumbers[(int) $b['id']] ?? null)) ?>
                        <?php if (($b['source'] ?? '') === 'migration'): ?><br><small class="muted">Existing stock before batch tracking</small><?php endif; ?></td>
                    <td data-label="Expiration date"><?= $expiry ? esc(\App\Libraries\DisplayDate::date($expiry)) : '<span class="muted">—</span>' ?>
                        <?php if ($expired): ?><span class="badge badge-danger">Expired</span><?php elseif ($near): ?><span class="badge badge-warning">Near</span><?php endif; ?></td>
                    <td data-label="Remaining"><?= number_format((int) $b['quantity_remaining']) ?> <?= esc($row['unit'] ?? '') ?></td>
                    <td data-label="Received"><?= number_format((int) $b['quantity_received']) ?></td>
                    <td data-label="Received on"><?= esc(\App\Libraries\DisplayDate::date($b['received_at'] ?? null)) ?></td>
                    <td data-label="Supplier"><?= esc($suppliers[$b['supplier_id'] ?? 0] ?? '—') ?></td>
                    <?php if ($canCorrect): ?>
                    <td data-label="Correct details">
                        <form class="batch-edit" method="post" action="<?= site_url('products/batches/correct/' . (int) $b['id']) ?>" data-action-review="batch" data-review-product="<?= esc($row['product_name'], 'attr') ?>" data-review-branch="<?= esc($row['branch_name'], 'attr') ?>" data-review-batch="<?= esc(\App\Libraries\StockBatches::label($b['batch_no'] ?? null, $batchNumbers[(int) $b['id']] ?? null), 'attr') ?>" data-original-lot="<?= esc($b['batch_no'] ?? '', 'attr') ?>" data-original-expiry="<?= esc($expiry ?? '', 'attr') ?>">
                            <?= csrf_field() ?>
                            <input class="input" type="text" name="batch_no" maxlength="60" value="<?= esc($b['batch_no'] ?? '', 'attr') ?>" placeholder="Lot no." aria-label="Lot number">
                            <input class="input" type="date" name="expiration_date" value="<?= esc($expiry ?? '', 'attr') ?>" aria-label="Expiration date">
                            <input class="input" type="text" name="reason" minlength="3" maxlength="150" required placeholder="Reason for correction" aria-label="Reason for correction">
                            <button class="btn secondary" type="submit">Save</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$batches): ?><tr><td colspan="<?= $canCorrect ? 7 : 6 ?>">No batches with stock. Use Add stock on the Products list to record a delivery.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>

    <div class="batch-actions">
        <a class="btn secondary" href="<?= site_url('products?branch_id=' . (int) $row['branch_id']) ?>">Back to products</a>
        <a class="btn secondary" href="<?= site_url('products/batches/' . (int) $row['id'] . ($showAll ? '' : '?all=1')) ?>"><?= $showAll ? 'Only batches with stock' : 'Include used-up batches' ?></a>
        <?php if (session('role') === 'admin'): ?><a class="btn secondary" href="<?= site_url('admin/expiry-report?branch_id=' . (int) $row['branch_id']) ?>">Expiry report</a><?php endif; ?>
    </div>
</div>
<?php if ($canCorrect): ?><?= view('partials/product_action_review', [], ['saveData' => false]) ?><?php endif; ?>
<?= $this->endSection() ?>
