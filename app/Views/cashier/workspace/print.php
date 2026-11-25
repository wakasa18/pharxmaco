<?php
    use App\Libraries\DisplayDate;
    $closed = $shift['status'] === 'closed';
    $settings = \App\Libraries\SystemSettings::DEFAULTS;
    $store = $settings['business_name'] . ' ' . $settings['business_type'];
    $reference = 'SHIFT-' . str_pad((string)$shift['id'], 6, '0', STR_PAD_LEFT);
    $generated = DisplayDate::dateTime($generatedAt);
    $money = static fn($value) => '₱' . number_format((float)$value, 2);
    $variance = static fn($value) => ((float)$value < 0 ? 'Short ' : ((float)$value > 0 ? 'Over ' : 'Balanced ')) . '₱' . number_format(abs((float)$value), 2);
    $closingActor = null;
    foreach ($actions as $action) if ($action['action'] === 'closed') $closingActor = $action['full_name'];
    $notes = ['Opening note' => $shift['opening_note'] ?? '', 'Closing note' => $shift['closing_note'] ?? '', 'Review note' => $review['review_note'] ?? ''];
    $continuedNotes = array_filter($notes, static fn($note) => mb_strlen(trim((string)$note)) > 160 || str_contains((string)$note,"\n") || str_contains((string)$note,"\r"));
    $printActivity = $printActivity ?? [];
    // Hex-escape every character so names cannot terminate a CSS content string.
    $cssText = static fn(string $text) => '"' . implode('',array_map(static fn($ch) => '\\'.dechex(mb_ord($ch)).' ',mb_str_split($text))) . '"';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= esc($reference) ?> - <?= $closed ? 'Closing' : 'Interim' ?> report</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/shift-print.css') ?>?v=20261016-2">
    <style>@page{
        @top-left{content:<?= $cssText($store.' · '.mb_strimwidth($shift['branch_name'],0,50,'…').' · '.$reference.' · '.($closed?'Final':'Interim').' · ') ?> counter(page) "/" counter(pages)}
        @bottom-left{content:<?= $cssText($reference.' · Generated '.$generated.' PHT') ?>}
    }</style>
    <script src="<?= base_url('assets/js/shift-print.js') ?>?v=20261016-1" defer></script>
</head>
<body class="sr-page" data-print-mode="summary">
    <nav class="sr-toolbar cw-print-actions" aria-label="Report printing">
        <button type="button" data-print-mode="summary">Print summary</button>
        <button type="button" data-print-mode="full">Print with details</button>
        <a href="<?= site_url('cashier/shifts/'.$shift['id']) ?>">Back to shift</a>
        <span>A4 · Philippine time · Details include all <?= number_format(count($printActivity)) ?> movements</span>
    </nav>
    <main class="sr-report cw-print">
        <section class="sr-summary" aria-label="Shift closing summary">
            <header class="sr-masthead">
                <div><p class="sr-store"><?= esc($store) ?></p><h1><?= $closed ? 'Shift closing report' : 'Interim shift report' ?></h1><p class="sr-branch"><?= esc($shift['branch_name']) ?> · <?= esc($shift['drawer_name']) ?></p></div>
                <div class="sr-document"><strong><?= esc($reference) ?></strong><span class="sr-status"><?= $closed ? 'Final · Closed' : 'Interim · Still open' ?></span><small>Generated <?= esc($generated) ?> PHT</small></div>
            </header>
            <dl class="sr-meta">
                <div><dt>Cashier</dt><dd><?= esc($shift['full_name']) ?></dd></div>
                <div><dt>Closed by</dt><dd><?= esc($closed ? ($closingActor ?: 'Not recorded') : 'Shift still open') ?></dd></div>
                <div><dt>Opened · PHT</dt><dd><?= esc(DisplayDate::dateTime($shift['opened_at'])) ?></dd></div>
                <div><dt>Closed · PHT</dt><dd><?= esc(DisplayDate::dateTime($shift['closed_at'])) ?></dd></div>
            </dl>
            <div class="sr-totals" aria-label="Shift totals">
                <?php foreach (['sales'=>'Sales recorded','transactions'=>'Sales transactions','units_sold'=>'Units sold','refunds'=>'Refund payouts','discounts'=>'Discounts','net_collections'=>'Net collections'] as $key=>$label): ?>
                <div><span><?= esc($label) ?></span><strong><?= in_array($key,['transactions','units_sold'],true) ? number_format((int)$summary[$key]) : $money($summary[$key]) ?></strong></div>
                <?php endforeach; ?>
            </div>
            <p class="sr-caption">Sales are after discounts and VAT relief (<?= $money($summary['vat_relief']) ?>). Refunds are separate. Net collections exclude the opening float and cash added/removed.</p>
            <div class="sr-columns">
                <section class="sr-panel"><h2>Cash drawer reconciliation</h2>
                    <dl class="sr-ledger">
                        <?php foreach (['Opening cash'=>$summary['opening_cash'],'Cash payments received'=>$summary['payments']['cash']['received'],
                            'Less cash payouts'=>-$summary['payments']['cash']['paid_out'],'Cash added'=>$summary['cash_added'],'Less cash removed'=>-$summary['cash_removed']] as $label=>$value): ?>
                        <div><dt><?= esc($label) ?></dt><dd><?= $money($value) ?></dd></div><?php endforeach; ?>
                        <div class="sr-emphasis"><dt>Expected cash</dt><dd><?= $money($summary['expected_cash']) ?></dd></div>
                        <?php if ($closed): ?>
                        <div><dt>Cash counted</dt><dd><?= $money($summary['counted_cash']) ?></dd></div>
                        <div class="sr-variance"><dt>Closing over / short</dt><dd><?= $variance($summary['cash_difference']) ?></dd></div>
                        <div><dt>Left in drawer</dt><dd><?= $money($summary['cash_left']) ?></dd></div>
                        <div><dt>Removed at closing</dt><dd><?= $money($summary['banked_cash']) ?></dd></div>
                        <?php else: ?><div><dt>Physical closing count</dt><dd>Not counted yet</dd></div><?php endif; ?>
                    </dl>
                    <p class="sr-caption">Opening cash + cash received − cash payouts + cash added − cash removed.</p>
                </section>
                <div class="sr-side">
                    <section class="sr-panel"><h2>Payment breakdown</h2>
                        <table class="sr-table sr-payment" aria-label="Payments"><thead><tr><th>Method</th><th>Received</th><th>Paid out</th><th>Net</th></tr></thead><tbody>
                        <?php foreach ($summary['payments'] as $method=>$payment): ?><?php if ($method==='unknown' && !$payment['received'] && !$payment['paid_out']) continue; ?>
                        <tr><th scope="row"><?= esc(['cash'=>'Cash','gcash'=>'GCash','card'=>'Card','unknown'=>'Unknown'][$method] ?? ucfirst($method)) ?></th><td><?= $money($payment['received']) ?></td><td><?= $money($payment['paid_out']) ?></td><td><?= $money($payment['net']) ?></td></tr>
                        <?php endforeach; ?></tbody></table>
                        <p class="sr-caption">Digital payments are excluded from physical drawer cash.</p>
                    </section>
                    <section class="sr-panel"><h2>Opening handover</h2><dl class="sr-ledger">
                        <div><dt>Previous cash left / expected</dt><dd><?= $shift['opening_expected']===null ? 'Not recorded' : $money($shift['opening_expected']) ?></dd></div>
                        <div><dt>Opening physical count</dt><dd><?= $money($shift['opening_cash']) ?></dd></div>
                        <div><dt>Opening over / short</dt><dd><?= $shift['opening_expected']===null ? 'Not applicable' : $variance($shift['opening_difference']) ?></dd></div>
                    </dl></section>
                </div>
            </div>
            <div class="sr-approval">
                <p><strong>Handover:</strong> <?php if ($shift['handover_to_user_id']): ?><?= esc($shift['handover_name'] ?: 'Cashier #'.$shift['handover_to_user_id']) ?> · <?= esc(ucfirst($shift['handover_status'] ?: 'Not recorded')) ?><?= $shift['handover_accepted_shift_id'] ? ' · Shift #'.(int)$shift['handover_accepted_shift_id'] : '' ?><?php else: ?><?= $closed ? 'No named recipient' : 'Recorded when the shift closes' ?><?php endif; ?></p>
                <p><strong>Manager review:</strong> <?= $review ? esc($review['full_name']).' · '.esc(DisplayDate::dateTime($review['reviewed_at'])) : ($closed ? 'Awaiting review' : 'Pending shift close') ?></p>
                <?php foreach ($notes as $label=>$note): ?><?php if (trim((string)$note)==='') continue; ?>
                <?php $summaryNote = preg_replace('/\s+/u',' ',trim($note)); ?>
                <p><strong><?= esc($label) ?>:</strong> <?= esc(isset($continuedNotes[$label]) ? mb_substr($summaryNote,0,160).'… See notes continuation.' : $summaryNote) ?></p>
                <?php endforeach; ?>
            </div>
            <div class="sr-signatures" aria-label="Shift sign-off">
                <div><span class="sr-sign-line"></span><strong>Cashier signature</strong><small><?= esc($shift['full_name']) ?> · Date / time: __________________</small></div>
                <div><span class="sr-sign-line"></span><strong>Receiving cashier / manager</strong><small>Name: __________________ · Date / time: __________________</small></div>
            </div>
            <p class="sr-summary-end"><?= $closed ? 'Saved closing totals' : 'Interim figures only; the drawer remains open' ?> · Details available with “Print with details”.</p>
        </section>
        <?php if ($continuedNotes): ?><section class="sr-notes sr-appendix"><header class="sr-section-header"><span><?= esc($reference) ?> · <?= esc($shift['branch_name']) ?></span><h2>Notes continuation</h2></header>
            <?php foreach ($continuedNotes as $label=>$note): ?><h3><?= esc($label) ?></h3><p><?= nl2br(esc($note)) ?></p><?php endforeach; ?>
        </section><?php endif; ?>
        <div class="sr-details" aria-label="Complete shift details">
            <section class="sr-appendix"><header class="sr-section-header"><span><?= esc($reference) ?> · <?= esc($shift['branch_name']) ?></span><h2>Products sold and returned</h2><p><?= number_format(count($summary['products'])) ?> products · Amounts after discounts and VAT relief, with returns deducted.</p></header>
                <div class="sr-table-wrap"><table class="sr-table sr-products" aria-label="Product movements"><thead><tr><th>Product</th><th>Sold</th><th>Returned</th><th>Replaced</th><th>Qty corrections</th><th>Amount</th></tr></thead><tbody data-product-rows>
                <?php foreach ($summary['products'] as $product): ?><tr><td><?= esc($product['name']) ?></td><td><?= (int)$product['sold'] ?></td><td><?= (int)$product['returned'] ?></td><td><?= (int)$product['replacements'] ?></td><td><?= (int)$product['quantity_adjustments'] ?></td><td><?= $money($product['amount']) ?></td></tr><?php endforeach; ?>
                <?php if (!$summary['products']): ?><tr><td colspan="6">No product movements recorded.</td></tr><?php endif; ?></tbody></table></div>
            </section>
            <section class="sr-appendix"><header class="sr-section-header"><span><?= esc($reference) ?> · <?= esc($shift['branch_name']) ?></span><h2>Complete shift activity</h2><p>All <?= number_format(count($printActivity)) ?> movements · Oldest first · Opening float and closing removal appear on the summary.</p></header>
                <div class="sr-table-wrap"><table class="sr-table sr-activity" aria-label="Complete cashier activity"><thead><tr><th>Date / time · PHT</th><th>Activity</th><th>Invoice / reason</th><th>Method</th><th>Amount</th></tr></thead><tbody data-activity-rows>
                <?php foreach ($printActivity as $entry): ?><tr><td><?= esc(DisplayDate::dateTime($entry['time'])) ?></td><td><?= esc(ucwords(str_replace('_',' ',$entry['type']))) ?> <span class="sr-movement">#<?= (int)$entry['id'] ?></span></td><td><?= esc($entry['invoice']) ?><?php if ($entry['reason']): ?><small><?= esc($entry['reason']) ?></small><?php endif; ?></td><td><?= esc(strtoupper($entry['method'])) ?></td><td><?= $money($entry['amount']) ?></td></tr><?php endforeach; ?>
                <?php if (!$printActivity): ?><tr><td colspan="5">No activity recorded.</td></tr><?php endif; ?></tbody></table></div>
            </section>
        </div>
    </main>
</body></html>
