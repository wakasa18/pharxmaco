<?php
$summary = \App\Libraries\ReportPdf::reconciliation($summary ?? []);
$money = static fn ($value): string => '₱' . number_format((float) $value, 2);
$hasAdjustments = abs($summary['other_adjustments']) >= .005;
?>
<section class="rp-panel rp-sales-summary" aria-label="Sales calculation">
    <dl class="rp-ledger" aria-label="Sales reconciliation">
        <div>
            <dt><span class="rp-ledger-icon"><?= view('partials/icon', ['name' => 'receipt']) ?></span>Original sales</dt>
            <dd><?= esc($money($summary['original_sales'])) ?></dd>
            <dd class="rp-sub">Recorded sales before discounts, VAT exemption and refunds</dd>
        </div>
        <div>
            <dt><span class="rp-ledger-icon"><?= view('partials/icon', ['name' => 'tag']) ?></span>Discounts</dt>
            <dd><?= esc($money($summary['total_discount'] ?? 0)) ?></dd>
            <dd class="rp-sub">Discounts deducted from these sales</dd>
        </div>
        <div>
            <dt><span class="rp-ledger-icon"><?= view('partials/icon', ['name' => 'tag']) ?></span>VAT exemption</dt>
            <dd><?= esc($money($summary['total_vat_relief'])) ?></dd>
            <dd class="rp-sub">Recorded VAT removed for eligible Senior/PWD purchases</dd>
        </div>
        <div>
            <dt><span class="rp-ledger-icon"><?= view('partials/icon', ['name' => 'receipt']) ?></span>Refunds against sales</dt>
            <dd><?= esc($money($summary['total_refunds'])) ?></dd>
            <dd class="rp-sub">All recorded refunds against the selected sales</dd>
        </div>
        <div>
            <dt><span class="rp-ledger-icon"><?= view('partials/icon', ['name' => 'cart']) ?></span>Transactions</dt>
            <dd><?= esc(number_format((int) ($summary['total_transactions'] ?? 0))) ?></dd>
            <dd class="rp-sub">Completed and partially refunded sales in the selected dates</dd>
        </div>
        <div>
            <dt><span class="rp-ledger-icon"><?= view('partials/icon', ['name' => 'calculator']) ?></span>Average sale</dt>
            <dd><?= esc($money($averageTransactionValue ?? 0)) ?></dd>
            <dd class="rp-sub">Net sales divided by the transaction count</dd>
        </div>
    </dl>
    <?php if ($hasAdjustments): ?>
        <p class="rp-reconciliation-note">Other adjustments: <strong><?= esc($money($summary['other_adjustments'])) ?></strong>. Differences in recorded invoice totals are shown separately from VAT exemption.</p>
    <?php endif; ?>
    <p class="rp-reconciliation-note">
        <span>Net sales = original sales − discounts − VAT exemption − refunds<?= $hasAdjustments ? ' − other adjustments' : '' ?>.</span>
        <strong>Net sales: <?= esc($money($summary['net_sales'] ?? 0)) ?></strong>
    </p>
</section>
