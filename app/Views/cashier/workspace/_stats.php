<?php
$peso = static fn($value) => ((float) $value < 0 ? '−' : '') . '₱' . number_format(abs((float) $value), 2);
$icons = [
    'sales' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
    'transactions' => '<path d="M4 7h14l-3-3M20 17H6l3 3"/>',
    'units_sold' => '<path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
    'refunds' => '<path d="M9 14L4 9l5-5"/><path d="M4 9h10a6 6 0 010 12h-3"/>',
    'net_collections' => '<path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
    'discounts' => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1"/>',
];
$metrics = ['sales' => ['Sales recorded','money','After discounts and VAT relief; returns shown separately.','primary'],
    'transactions' => ['Sales transactions','count','Each completed sale counted once.','muted'], 'units_sold' => ['Units sold','count','Replacement units appear in product movements.','muted'],
    'refunds' => ['Refund payouts','money','Refunds processed in this period.','danger'], 'net_collections' => ['Net collections','money','Payments received less payouts; exchange credit excluded.','ok'],
    'discounts' => ['Discounts on new sales','money','VAT relief: ' . $peso($report['vat_relief'] ?? 0),'warn']]; ?>
<div class="cw-stats" aria-label="Cashier sales summary">
    <?php foreach ($metrics as $key => [$label,$format,$help,$tone]): ?><div class="cw-stat<?= $key === 'net_collections' ? ' is-featured' : '' ?>" data-tone="<?= $tone ?>">
        <div class="cw-stat-top"><span class="cw-stat-icon" aria-hidden="true"><svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $icons[$key] ?></svg></span><span class="cw-stat-label"><?= esc($label) ?></span></div>
        <strong data-report-metric="<?= esc($key) ?>" data-format="<?= $format ?>"><?= $format === 'money' ? $peso($report[$key]) : number_format((int) $report[$key]) ?></strong>
        <small<?= $key === 'discounts' ? ' data-vat-help' : '' ?>><?= esc($help) ?></small>
    </div><?php endforeach; ?>
</div>
