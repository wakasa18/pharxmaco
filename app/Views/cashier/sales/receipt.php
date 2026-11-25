<?php
    $settings = \App\Libraries\SystemSettings::DEFAULTS;
    $exchange = \App\Libraries\ExchangeRecord::forSale($sale);
    $isCopy = !empty($isReprint);
    $invoice = \App\Libraries\ReceiptInvoice::data($sale, $items);
    $documentType = 'SALES INVOICE' . ($exchange ? ' · Exchange' : '') . ($isCopy ? ' · REPRINT' : '');
    $status = $sale['status'] ?? 'completed';
    $statusLabel = ['completed' => 'Payment recorded', 'refunded' => 'Fully refunded', 'partially_refunded' => 'Partially refunded'][$status] ?? ucfirst($status);
    $hasRefund = in_array($status, ['refunded', 'partially_refunded'], true);
    $extraActions = $hasRefund ? [['label' => 'Latest refund slip', 'url' => site_url('cashier/sales/refund-slip/' . (int) $sale['id'])]] : [];
    if ($exchange) {
        $extraActions[] = ['label' => 'Original receipt', 'url' => site_url('cashier/sales/receipt/' . $exchange['original_id'])];
        $extraActions[] = ['label' => 'Exchange return slip', 'url' => site_url('cashier/sales/refund-slip/' . $exchange['original_id'] . '?event=' . $exchange['event_id'])];
    }
?>
<!DOCTYPE html>
<html lang="en" data-ui-settings-key="<?= esc(session('user_id') ? 'pharxmaco-ui-settings:user:' . (int) session('user_id') : 'pharxmaco-ui-settings', 'attr') ?>">
<head>
    <link rel="icon" type="image/jpeg" href="<?= base_url('assets/images/pharxmaco-favicon.jpg') ?>?v=20260923">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($documentType) ?> — <?= esc($sale['invoice_no']) ?></title>
    <script src="<?= base_url('assets/js/receipt-theme.js') ?>?v=20261012"></script>
    <link rel="stylesheet" href="<?= base_url('assets/css/receipts.css') ?>?v=20261014-1">
    <script src="<?= base_url('assets/js/receipts.js') ?>" defer></script>
</head>
<body class="receipt-page" data-paper-width="80" data-auto-print="<?= !empty($autoPrint) ? 'true' : 'false' ?>">
<div class="receipt-shell">
    <?= view('partials/receipt_toolbar', ['heading' => $exchange ? 'Exchange invoice' : ($isCopy ? 'Invoice reprint' : 'Sales invoice'), 'subtitle' => $exchange ? 'Returned items, replacements and settlement.' : 'Your purchase, item by item.']) ?>
    <main class="receipt-layout">
        <div class="receipt-stage">
            <article class="receipt-paper" aria-label="Sales invoice <?= esc($invoice['serial']) ?>">
                <?= view('partials/receipt_header', ['businessName' => $settings['business_name'], 'businessType' => $settings['business_type'], 'documentType' => $documentType, 'sale' => $sale, 'hideAddress' => false], ['saveData' => false]) ?>
                <div class="receipt-status <?= $hasRefund ? 'receipt-status--refund' : '' ?>"><?= esc($statusLabel) ?></div>
                <hr class="receipt-rule">
                <dl class="receipt-meta">
                    <dt>Invoice serial no.</dt><dd><?= esc($invoice['series_recorded'] ? $invoice['serial'] : ($invoice['recorded'] ? 'Not configured' : 'Not recorded')) ?></dd>
                    <?php if (!$invoice['series_recorded'] || $invoice['serial'] !== $sale['invoice_no']): ?><dt>Transaction ref.</dt><dd><?= esc($sale['invoice_no']) ?></dd><?php endif; ?>
                    <dt>TIN</dt><dd><?= esc($invoice['tin']) ?></dd>
                    <dt>Tax status</dt><dd><?= esc($invoice['tax_status']) ?></dd>
                    <?php if ($invoice['beneficiary']): ?>
                    <dt><?= esc(\App\Libraries\StatutoryDiscount::OPTIONS[$invoice['beneficiary']['kind']]) ?></dt><dd><?= esc($invoice['beneficiary']['name'] ?? '') ?></dd>
                    <dt>Beneficiary ID</dt><dd><?= esc($invoice['beneficiary']['id'] ?? '') ?></dd>
                    <?php endif; ?>
                    <?php if ($exchange): ?><dt>Exchange for</dt><dd><?= esc($exchange['original_invoice']) ?></dd><?php endif; ?>
                    <dt>Date &amp; time</dt><dd><?= \App\Libraries\DisplayDate::dateTime($sale['sale_date']) ?></dd>
                    <dt>Cashier</dt><dd><?= esc($sale['full_name'] ?? '—') ?></dd>
                    <dt>Payment method</dt><dd><?= esc(strtoupper($sale['payment_method'])) ?></dd>
                    <?php if (!empty($sale['reference_no'])): ?><dt>Reference number</dt><dd><?= esc($sale['reference_no']) ?></dd><?php endif; ?>
                </dl>
                <hr class="receipt-rule">
                <table class="receipt-items" aria-label="Purchased items">
                    <thead><tr><th scope="col">Item</th><th scope="col">Amount</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <div class="receipt-item-name"><?= esc($item['product_name_snapshot']) ?></div>
                                <?php if (!empty($item['generic_name_snapshot'])): ?><div class="receipt-item-detail">Generic: <?= esc($item['generic_name_snapshot']) ?></div><?php endif; ?>
                                <?php if (!empty($item['strength_snapshot'])): ?><div class="receipt-item-detail">Strength: <?= esc($item['strength_snapshot']) ?></div><?php endif; ?>
                                <div class="receipt-item-detail"><?= (int) $item['quantity'] ?> × ₱<?= number_format((float) $item['price'], 2) ?></div>
                                <?php if ((float) ($item['discount_applied'] ?? 0) > 0): ?><div class="receipt-item-detail">Discount: −₱<?= number_format((float) $item['discount_applied'], 2) ?></div><?php endif; ?>
                                <?php if ((float) ($item['vat_exemption_amount'] ?? 0) > 0): ?><div class="receipt-item-detail">VAT removed: −₱<?= number_format((float) $item['vat_exemption_amount'], 2) ?></div><?php endif; ?>
                            </td>
                            <td>₱<?= number_format((float) $item['subtotal'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <table class="receipt-totals" aria-label="Payment totals">
                    <tr><td>Subtotal</td><td>₱<?= number_format((float) $sale['total_amount'], 2) ?></td></tr>
                    <?php if ($invoice['vat_exemption'] > 0): ?><tr><td>VAT exemption</td><td>−₱<?= number_format($invoice['vat_exemption'], 2) ?></td></tr><?php endif; ?>
                    <?php if ($invoice['discount_breakdown']): ?>
                    <?php if ($invoice['discount_breakdown']['promotion'] > 0): ?><tr><td>Promotions · <?= esc($invoice['discount_breakdown']['promotion_name']) ?></td><td>−₱<?= number_format($invoice['discount_breakdown']['promotion'], 2) ?></td></tr><?php endif; ?>
                    <?php if ($invoice['discount_breakdown']['statutory'] > 0): ?><tr><td><?= esc($invoice['discount_breakdown']['statutory_name']) ?> discount</td><td>−₱<?= number_format($invoice['discount_breakdown']['statutory'], 2) ?></td></tr><?php endif; ?>
                    <?php elseif ((float) $sale['discount_amount'] > 0): ?><tr><td>Discount<?= $invoice['discount_name'] !== '' ? ' · ' . esc($invoice['discount_name']) : '' ?></td><td>−₱<?= number_format((float) $sale['discount_amount'], 2) ?></td></tr><?php endif; ?>
                    <tr class="receipt-grand"><td><?= $hasRefund ? 'Original sale total' : 'Total paid' ?></td><td>₱<?= number_format((float) $sale['final_total'], 2) ?></td></tr>
                    <?php if ($exchange): ?>
                    <tr><td>Return value</td><td>₱<?= number_format($exchange['returned'], 2) ?></td></tr>
                    <tr><td>Credit applied</td><td>₱<?= number_format($exchange['credit'], 2) ?></td></tr>
                    <tr><td>Additional payment</td><td>₱<?= number_format($exchange['due'], 2) ?></td></tr>
                    <tr><td>Paid back to customer</td><td>₱<?= number_format($exchange['payout'], 2) ?></td></tr>
                    <?php else: ?>
                    <tr><td><?= $sale['payment_method'] === 'cash' ? 'Cash received' : 'Amount received' ?></td><td>₱<?= number_format((float) $sale['amount_paid'], 2) ?></td></tr>
                    <tr><td>Change</td><td>₱<?= number_format((float) $sale['change_amount'], 2) ?></td></tr>
                    <?php endif; ?>
                </table>
                <section class="receipt-tax" aria-label="Tax breakdown">
                    <h2>Tax breakdown</h2>
                    <?php if ($invoice['tax_recorded']): ?>
                        <table class="receipt-totals" aria-label="Tax amounts">
                            <?php foreach ($invoice['tax_rows'] as $label => $amount): ?><tr><td><?= esc($label) ?></td><td>₱<?= number_format($amount, 2) ?></td></tr><?php endforeach; ?>
                        </table>
                    <?php else: ?><p class="receipt-item-detail"><?= $invoice['recorded'] && $invoice['tax_status'] === 'Not configured' ? 'Tax status has not been configured.' : 'No reconciled tax breakdown was recorded for this sale.' ?></p><?php endif; ?>
                </section>
                <?php if ($invoice['tax_status'] === 'Non-VAT'): ?><p class="receipt-tax-notice">THIS DOCUMENT IS NOT VALID FOR CLAIM OF INPUT TAX.</p><?php endif; ?>
                <?php if ($hasRefund): ?><div class="receipt-note">This receipt shows the original sale. Refund amounts and payout details appear on the separate refund slips.</div><?php endif; ?>
                <?php if (!empty($sale['notes'])): ?><div class="receipt-note"><strong>Notes</strong><?= nl2br(esc($sale['notes'])) ?></div><?php endif; ?>
                <hr class="receipt-rule">
                <footer class="receipt-footer">
                    <div class="receipt-thanks"><?= esc($settings['receipt_thank_you']) ?></div>
                    <?= nl2br(esc($settings['receipt_policy'])) ?>
                    <span class="receipt-reference"><?= esc($sale['invoice_no']) ?></span>
                    <?= esc($settings['business_name']) ?> POS<?= $isCopy ? ' · Reprinted copy' : '' ?>
                </footer>
            </article>
            <p class="receipt-view-note">Enlarged for easy reading. Prints on <span data-paper-label>80 mm</span> paper.</p>
        </div>
        <?= view('partials/receipt_print_options', ['sale' => $sale, 'printLabel' => $isCopy ? 'Print copy' : 'Print invoice', 'extraActions' => $extraActions]) ?>
    </main>
</div>
</body>
</html>
