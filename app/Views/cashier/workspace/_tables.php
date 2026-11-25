<?php $interactiveTables = !empty($dataTables); $tableClass = $interactiveTables ? ' js-datatable' : ''; $scrollClass = $interactiveTables ? ' table-wrap' : ''; $liveAttribute = $interactiveTables ? ' data-dt-live' : '';
$peso = static fn($value) => ((float) $value < 0 ? '−' : '') . '₱' . number_format(abs((float) $value), 2);
$negative = static fn($value) => (float) $value < 0 ? ' is-neg' : '';
$activityTone = ['sale' => 'ok', 'refund' => 'bad', 'exchange' => 'info', 'adjustment' => 'warn', 'cash_in' => 'ok', 'cash_out' => 'warn']; ?>
<section class="cw-card"><div class="cw-card-head"><h2>Payment breakdown</h2><p>Actual receipts and payouts. Opening cash and cash additions/removals are tracked in the drawer ledger.</p></div>
    <div class="cw-table-scroll<?= $scrollClass ?>"><table class="cw-table<?= $tableClass ?>"<?= $liveAttribute ?> data-dt-mode="simple" aria-label="Payments"><thead><tr><th>Method</th><th class="money">Received</th><th class="money">Paid out</th><th class="money">Net</th></tr></thead><tbody data-payment-rows>
        <?php foreach ($report['payments'] as $method => $payment): ?><?php if ($method === 'unknown' && !$payment['paid_out']) continue; ?>
        <tr><td><span class="cw-dot cw-dot-<?= esc($method, 'attr') ?>" aria-hidden="true"></span><?= esc(['cash'=>'Cash','gcash'=>'GCash','card'=>'Card','unknown'=>'Unknown (older refund)'][$method]) ?></td><td class="money"><?= $peso($payment['received']) ?></td><td class="money"><?= $peso($payment['paid_out']) ?></td><td class="money<?= $negative($payment['net']) ?>"><?= $peso($payment['net']) ?></td></tr>
        <?php endforeach; ?></tbody></table></div>
</section>
<section class="cw-card"><div class="cw-card-head"><h2>Products sold and returned</h2><p>Recorded product names and amounts after discounts and VAT relief. Return values are deducted from the amount column.</p></div>
    <div class="cw-table-scroll<?= $scrollClass ?>"><table class="cw-table cw-table-wide<?= $tableClass ?>"<?= $liveAttribute ?> data-dt-empty="No products recorded in this period. Completed sales will appear here." aria-label="Product movements"><thead><tr><th>Product</th><th class="number">Sold</th><th class="number">Returned</th><th class="number">Replacements</th><th class="number">Qty corrections</th><th class="money">Amount</th></tr></thead><tbody data-product-rows>
        <?php foreach ($report['products'] as $product): ?><tr><td><?= esc($product['name']) ?></td><td class="number"><?= (int) $product['sold'] ?></td><td class="number<?= (int) $product['returned'] > 0 ? ' is-neg' : '' ?>"><?= (int) $product['returned'] ?></td><td class="number"><?= (int) $product['replacements'] ?></td><td class="number"><?= (int) $product['quantity_adjustments'] ?></td><td class="money<?= $negative($product['amount']) ?>"><?= $peso($product['amount']) ?></td></tr><?php endforeach; ?>
        <?php if (!$report['products']): ?><tr><td colspan="6" class="cw-empty">No products recorded in this period. Completed sales will appear here.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<section class="cw-card"><div class="cw-card-head"><h2>Recent activity</h2><p data-activity-caption>Latest 100 movements in this period, including <?= (int) $report['refund_count'] ?> refund event(s) and <?= (int) $report['exchange_count'] ?> exchange(s).</p></div>
    <div class="cw-table-scroll<?= $scrollClass ?>"><table class="cw-table cw-table-wide<?= $tableClass ?>"<?= $liveAttribute ?> data-dt-empty="No activity recorded in this period." aria-label="Cashier activity"><thead><tr><th>When</th><th>Activity</th><th>Invoice / reason</th><th>Method</th><th class="money">Amount</th></tr></thead><tbody data-activity-rows>
        <?php foreach ($report['activity'] as $entry): ?><tr><td class="cw-date"><?= esc(\App\Libraries\DisplayDate::dateTime($entry['time'])) ?></td><td><span class="cw-badge is-<?= $activityTone[$entry['type']] ?? 'muted' ?>"><?= esc(ucwords(str_replace('_',' ', $entry['type']))) ?></span><?php if ($entry['legacy']): ?><small>Before shift tracking</small><?php endif; ?></td><td>
            <?php if ($entry['sale_id']): ?><a href="<?= site_url('cashier/sales/view/' . (int) $entry['sale_id']) ?>"><?= esc($entry['invoice'] ?: 'View sale') ?></a><?php endif; ?>
            <?php if ($entry['reason']): ?><small><?= esc($entry['reason']) ?></small><?php endif; ?></td><td><?= esc(strtoupper($entry['method'])) ?></td><td class="money<?= $negative($entry['amount']) ?>"><?= $peso($entry['amount']) ?></td></tr><?php endforeach; ?>
        <?php if (!$report['activity']): ?><tr><td colspan="5" class="cw-empty">No activity recorded in this period.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
