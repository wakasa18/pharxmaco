<?= $this->extend('layouts/staff') ?>
<?= $this->section('content') ?>

<div class="page-header" style="margin-bottom:20px;">
    <h1 style="margin:0;">Stock Logs</h1>
    <p class="muted" style="margin:6px 0 0;">Track stock movements per branch.</p>
</div>


<?php if (!empty($filterNotices)): ?>
    <div class="alert warning" style="margin-bottom:16px;">
        <strong>Filter notice:</strong> <?= esc(implode(' ', $filterNotices)) ?>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert success">
        <?= esc(session()->getFlashdata('success')) ?>
    </div>
    <div class="sp"></div>
<?php endif; ?>

<?php
$flashError = session()->getFlashdata('error');
$isAdminOnlyMsg = str_contains((string)$flashError, 'Admin only');
if ($flashError && !(session('role') !== 'admin' && $isAdminOnlyMsg)):
?>
    <div class="alert danger">
        <?= esc($flashError) ?>
    </div>
    <div class="sp"></div>
<?php endif; ?>

<div class="card">
    <div class="card-head">
        <div>
            <h2 style="margin:0;">Stock Log Records</h2>
            <p class="muted" style="margin:6px 0 0;">Search, filter, and export stock movement records.</p>
        </div>
    </div>

    <div class="dt-export-bar">
        <a href="<?= site_url('stock-logs/export-excel?search=' . urlencode($search ?? '') . '&branch_id=' . urlencode($filterBranch ?? '') . '&action_type=' . urlencode($filterActionType ?? '') . '&date_from=' . urlencode($dateFrom ?? '') . '&date_to=' . urlencode($dateTo ?? '')) ?>"
                class="btn secondary">
                Excel CSV
            </a>
        <a href="<?= site_url('stock-logs/export-pdf?search=' . urlencode($search ?? '') . '&branch_id=' . urlencode($filterBranch ?? '') . '&action_type=' . urlencode($filterActionType ?? '') . '&date_from=' . urlencode($dateFrom ?? '') . '&date_to=' . urlencode($dateTo ?? '')) ?>"
                class="btn btn-secondary">
                Export PDF
            </a>
    </div>


    <?php if (!empty($tableTruncated)): ?>
        <p class="dt-cap-note" role="status">This list is very long, so only the first <?= number_format(3000) ?> records are loaded here.</p>
    <?php endif; ?>
    <div class="table-wrap">
        <table class="mobile-record-table js-datatable" data-record-title="1" aria-label="Stock logs" data-dt-length="25">
            <thead>
                <tr>
                    <th style="width:70px;">ID</th>
                    <th>Product</th>
                    <th>Branch</th>
                    <th>User</th>
                    <th>Type</th>
                    <th>Qty</th>
                    <th>Prev</th>
                    <th>New</th>
                    <th>Remarks</th>
                    <th style="min-width:170px;">Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logs)): ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= esc($log['id']) ?></td>
                            <td><strong><?= esc($log['product_name'] ?? '-') ?></strong></td>
                            <td><?= esc($log['branch_name'] ?? '-') ?></td>
                            <td><?= esc($log['full_name'] ?? '-') ?></td>
                            <td>
                                <?php if ($log['action_type'] === 'stock_in'): ?>
                                    <span class="badge badge-success">Stock In</span>
                                <?php elseif ($log['action_type'] === 'stock_out'): ?>
                                    <span class="badge badge-danger">Stock Out</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">
                                        <?= esc(ucwords(str_replace('_', ' ', $log['action_type']))) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($log['quantity']) ?></td>
                            <td><?= esc($log['previous_stock']) ?></td>
                            <td><?= esc($log['new_stock']) ?></td>
                            <td><?= esc($log['remarks'] ?? '-') ?><?php if (!empty($log['batch_id'])): ?><br><small class="muted">Batch <?= esc(($log['batch_no'] ?? '') !== '' ? $log['batch_no'] : '#' . (int) $log['batch_id']) ?><?= !empty($log['batch_expiry']) ? ' · exp ' . esc(\App\Libraries\DisplayDate::date($log['batch_expiry'])) : '' ?></small><?php endif; ?></td>
                            <td><?= esc(\App\Libraries\DisplayDate::dateTime($log['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="muted" style="text-align:center; padding:20px;">
                            No stock logs found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    </div>

<?= $this->endSection() ?>