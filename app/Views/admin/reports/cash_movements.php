<?php
$movement = $cashMovements ?? ['rows' => [], 'totals' => [], 'returns' => []];
$money = static fn ($value): string => ((float) $value < 0 ? '−' : '') . '₱' . number_format(abs((float) $value), 2);
?>
<section class="rp-panel rp-cash" aria-labelledby="cashMovementsTitle">
    <div class="rp-panel-head">
        <div>
            <h3 id="cashMovementsTitle">Receipts and refund payouts by date</h3>
            <p>Sales use the sale date; payouts use the refund date, including refunds for older invoices. Amounts exclude cash change and exchange credits; exchanges show only additional payments or payouts.</p>
        </div>
    </div>
    <dl class="rp-cash-summary">
        <div><dt>Receipts</dt><dd><?= $money($movement['totals']['receipts'] ?? 0) ?></dd></div>
        <div><dt>Refund payouts</dt><dd><?= $money($movement['totals']['refunds'] ?? 0) ?></dd></div>
        <div class="is-net"><dt>Net movement</dt><dd><?= $money($movement['totals']['net'] ?? 0) ?></dd></div>
        <div><dt>Recorded cash movement</dt><dd><?= $money($movement['totals']['cash_net'] ?? 0) ?></dd></div>
    </dl>
    <?php if (($movement['totals']['unknown_refunds'] ?? 0) > 0): ?>
        <p class="rp-note is-warning">Older refunds totaling ₱<?= number_format($movement['totals']['unknown_refunds'], 2) ?> have no recorded payout method. Review these before reconciling the cash drawer.</p>
    <?php endif; ?>
    <div class="table-wrap rp-cash-scroll" role="region" aria-labelledby="cashMovementsTitle" tabindex="0">
        <table class="data small mobile-record-table rp-table js-datatable" data-dt-mode="simple">
            <thead><tr><th>Date</th><th>Payment method</th><th class="num">Receipts</th><th class="num">Refund payouts</th><th class="num">Net movement</th></tr></thead>
            <tbody>
                <?php foreach ($movement['rows'] as $row): ?>
                    <tr>
                        <td><?= esc(\App\Libraries\DisplayDate::date($row['date'])) ?></td>
                        <td><?= esc($row['method'] === 'unknown' ? 'Unknown (legacy refund)' : strtoupper($row['method'])) ?></td>
                        <td class="num"><?= $money($row['receipts']) ?></td>
                        <td class="num"><?= $money($row['refunds']) ?></td>
                        <td class="num amount"><?= $money($row['net']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$movement['rows']): ?><tr><td colspan="5" class="rp-empty">No receipts or refund payouts in this period.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($movement['returns']): ?>
        <div class="rp-returns">
            <strong>Returned units recorded in this period</strong>
            <ul>
                <?php foreach ($movement['returns'] as $row): ?>
                    <li><span><?= esc(ucfirst($row['return_condition'])) ?></span> <b><?= (int) $row['quantity'] ?></b></li>
                <?php endforeach; ?>
            </ul>
            <p>Only resellable returns increase available stock. Other conditions remain in the return records.</p>
        </div>
    <?php endif; ?>
</section>
