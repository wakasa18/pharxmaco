<?= $this->extend('layouts/staff') ?>
<?= $this->section('content') ?>

<link rel="stylesheet" href="<?= base_url('assets/css/sales-history.css') ?>?v=20261020">
<script src="<?= base_url('assets/js/sales-history.js') ?>?v=20261020" defer></script>

<div class="page-header" style="margin-bottom:20px;">
    <h1 style="margin:0;">Sales History</h1>
    <p class="muted" style="margin:6px 0 0;">View sales, reprint receipts, and process refunds or product exchanges.
    </p>
</div>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert success">
        <?= esc(session()->getFlashdata('success')) ?>
    </div>
    <div class="sp"></div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert danger">
        <?= esc(session()->getFlashdata('error')) ?>
    </div>
    <div class="sp"></div>
<?php endif; ?>

<div class="card">
    <div class="card-head">
        <div>
            <h2 style="margin:0;">Transaction Records</h2>
            <p class="muted" style="margin:6px 0 0;">Search, filter, view, reprint, and refund completed sales.</p>
        </div>

        <div class="row" style="gap:10px;">
            <a class="btn secondary" href="<?= site_url('cashier/sales') ?>">← Back to POS</a>
            <?php if (session('role') === 'admin'): ?>
                <a class="btn secondary" href="<?= site_url('admin/reports') ?>">Reports</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="sales-history-filters">
        <form id="sales-history-range" method="get" action="<?= site_url('cashier/sales/history') ?>" data-auto-filter="off" aria-label="Filter sales by date" aria-describedby="sales-history-note<?= !empty($dateRangeError) ? ' sales-history-error' : '' ?>">
            <?php foreach (['q' => $keyword ?? '', 'branch_id' => $filterBranch ?? '', 'payment_method' => $filterPaymentMethod ?? '', 'status' => $filterStatus ?? ''] as $name => $value): ?>
                <?php if ($value !== ''): ?><input type="hidden" name="<?= esc($name, 'attr') ?>" value="<?= esc($value, 'attr') ?>"><?php endif; ?>
            <?php endforeach; ?>
            <label class="sales-history-date" for="history-date-from">
                <span>Sale date from</span>
                <input id="history-date-from" type="date" name="date_from" class="input" value="<?= esc($dateFrom, 'attr') ?>">
            </label>
            <label class="sales-history-date" for="history-date-to">
                <span>Sale date to</span>
                <input id="history-date-to" type="date" name="date_to" class="input" value="<?= esc($dateTo, 'attr') ?>">
            </label>
            <div class="sales-history-actions">
                <button type="submit" class="btn btn-primary">Apply</button>
                <a href="<?= site_url('cashier/sales/history') ?>" class="btn btn-secondary">Clear</a>
            </div>
        </form>
        <?php if (!empty($dateRangeError)): ?>
            <p id="sales-history-error" class="sales-history-error" role="alert"><?= esc($dateRangeError) ?></p>
        <?php endif; ?>
        <p id="sales-history-note" class="sales-history-note" role="status">
            <?php if (!empty($dateRangeError)): ?>
                No transactions loaded. Correct the date range to continue.
            <?php elseif (!empty($tableTruncated)): ?>
                Showing the latest <?= number_format((int) $rowLimit) ?> sales<?= ($dateFrom !== '' || $dateTo !== '') ? ' in this date range' : '' ?>. Narrow the date range to find older transactions.
            <?php else: ?>
                Showing all <?= number_format(count($sales ?? [])) ?> <?= count($sales ?? []) === 1 ? 'sale' : 'sales' ?><?= ($dateFrom !== '' || $dateTo !== '') ? ' in this date range' : '' ?>. Up to <?= number_format((int) $rowLimit) ?> sales are loaded at a time.
            <?php endif; ?>
            Quick search filters loaded transactions only.
        </p>
    </div>
    <?php
    // Built once: calling site_url() and session() for every row adds up on long lists.
    $isAdmin       = session('role') === 'admin';
    $viewUrl       = site_url('cashier/sales/view/__ID__');
    $reprintUrl    = site_url('cashier/sales/reprint/__ID__');
    $exchangeUrl   = site_url('cashier/sales/exchange/__ID__');
    $refundUrl     = site_url('cashier/sales/refund-form/__ID__');
    $correctionUrl = site_url('admin/sale-correction/__ID__');
    ?>
    <div class="table-wrap">
        <table class="mobile-record-table js-datatable" data-record-title="0" data-dt-date-filter="off" data-dt-link-ignore="date_from,date_to" data-dt-empty="No sales records found." aria-label="Sales history" aria-describedby="sales-history-note">
            <thead>
                <tr>
                    <th>Invoice No.</th>
                    <th>Branch</th>
                    <th>Cashier</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Sale Date</th>
                    <th style="min-width:220px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($sales)): ?>
                    <?php foreach ($sales as $s): ?>
                        <tr>
                            <td><strong><?= esc($s['invoice_no']) ?></strong></td>
                            <td><?= esc($s['branch_name'] ?? '-') ?></td>
                            <td><?= esc($s['full_name'] ?? '-') ?></td>
                            <td>₱<?= number_format((float) $s['final_total'], 2) ?></td>
                            <td><?= esc(ucfirst($s['payment_method'])) ?></td>
                            <td>
                                <?php if (($s['status'] ?? '') === 'completed'): ?>
                                    <span class="badge badge-success">Completed</span>
                                <?php elseif (($s['status'] ?? '') === 'refunded'): ?>
                                    <span class="badge badge-danger">Refunded</span>
                                <?php elseif (($s['status'] ?? '') === 'partially_refunded'): ?>
                                    <span class="badge badge-warning">Partial Refund</span>
                                <?php elseif (($s['status'] ?? '') === 'void'): ?>
                                    <span class="badge badge-danger">Void</span>
                                <?php else: ?>
                                    <span class="badge badge-warning"><?= esc(ucfirst($s['status'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= esc(\App\Libraries\DisplayDate::dateTime($s['sale_date'])) ?></td>
                            <td>
                                <div class="row" style="gap:8px; flex-wrap:wrap;">
                                    <a href="<?= str_replace('__ID__', (string) $s['id'], $viewUrl) ?>" class="btn btn-secondary">View Details</a>
                                    <?php if ($isAdmin && ($s['status'] ?? '') === 'completed' && !\App\Libraries\ExchangeRecord::identity($s)): ?>
                                    <a href="<?= str_replace('__ID__', (string) $s['id'], $correctionUrl) ?>"
                                        class="btn btn-secondary btn-sm"
                                        style="color:var(--primary);border-color:var(--primary);"
                                        title="Correct this sale record">Correct</a>
                                    <?php endif; ?>
                                    <a href="<?= str_replace('__ID__', (string) $s['id'], $reprintUrl) ?>"
                                        class="btn secondary">Reprint</a>

                                    <?php if (in_array(($s['status'] ?? ''), ['completed', 'partially_refunded'])): ?>
                                        <a href="<?= str_replace('__ID__', (string) (int) $s['id'], $exchangeUrl) ?>" class="btn btn-secondary">Exchange Product</a>
                                        <a href="<?= str_replace('__ID__', (string) $s['id'], $refundUrl) ?>"
                                           class="btn danger btn-sm">
                                            Refund
                                        </a>
                                    <?php else: ?>
                                        <span class="muted">No refund</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="muted" style="text-align:center; padding:20px;">
                            No sales records found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    </div>

<?= $this->endSection() ?>
