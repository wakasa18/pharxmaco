<?php
use App\Libraries\DisplayDate;
use App\Libraries\ReportPdf;
$money = static fn($value): string => '₱' . number_format((float)$value, 2);
$col = static fn(string $label, int $width, bool $num = false): array => compact('label', 'width', 'num');
$summary = ReportPdf::reconciliation($salesSummary ?? []);
$compact = ($pdfMode ?? 'full') === 'summary';
$hasSales = (int)($summary['total_transactions'] ?? 0) > 0;
$hasForecast = abs((float)($nextForecastValue ?? 0)) > .001;
foreach (($forecastHistory ?? []) as $row) $hasForecast = $hasForecast || abs((float)($row['net_sales'] ?? 0)) > .001;
$hasCosts = abs((float)($profitSummary['cogs'] ?? 0)) > .001 || abs((float)($profitSummary['gross_profit'] ?? 0)) > .001;
$table = static fn(string $title, array $columns, array $rows, string $note = ''): string => view('exports/pdf_table', compact('title', 'columns', 'rows', 'note'), ['saveData' => false]);
$metadata = [['Sales range', DisplayDate::date($reportDateFrom ?? '') . ' to ' . DisplayDate::date($reportDateTo ?? '')], ['Sales grouping', ucfirst($reportType ?? 'daily')]];
if (!$compact) {
    $metadata[] = ['Forecast history', DisplayDate::date($forecastDateFrom ?? '') . ' to ' . DisplayDate::date($forecastDateTo ?? '')];
    $metadata[] = ['Forecast grouping', ucfirst($forecastType ?? 'daily')];
}
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Sales and Forecast Report</title><?= view('exports/pdf_styles', [], ['saveData' => false]) ?></head><body class="<?= $compact ? 'summary-report' : '' ?>">
<?= view('exports/pdf_header', ReportPdf::header(get_defined_vars(), $compact ? 'Sales Summary' : 'Sales and Forecast Report', $metadata), ['saveData' => false]) ?>
<h3 class="section-title">Sales Reconciliation</h3>
<p class="section-note">PHP. Original sales use recorded selling prices, including VAT where charged. Remaining sales = original sales - discounts - VAT relief - refunds<?= abs($summary['other_adjustments']) >= .005 ? ' - other adjustments' : '' ?>.</p>
<table class="summary"><tr>
<?php foreach (['Original sales' => $summary['original_sales'], 'Discounts' => $summary['total_discount'] ?? 0, 'VAT relief' => $summary['total_vat_relief'], 'Refunds against sales' => $summary['total_refunds'], 'Remaining sales' => $summary['net_sales'] ?? 0] as $label => $value): ?>
<td><span class="label"><?= esc($label) ?></span><span class="value"><?= $money($value) ?></span></td>
<?php endforeach; ?></tr><tr>
<td><span class="label">Product cost</span><span class="value"><?= $money($profitSummary['cogs'] ?? 0) ?></span></td>
<td><span class="label">Recorded profit</span><span class="value"><?= $money($profitSummary['gross_profit'] ?? 0) ?></span></td>
<td><span class="label">Revenue transactions</span><span class="value"><?= (int)($summary['total_transactions'] ?? 0) ?></span></td>
<td><span class="label">Average remaining sale</span><span class="value"><?= $money($averageTransactionValue ?? 0) ?></span></td>
<td><span class="label"><?= $compact ? 'Recorded cash movement' : 'Low-stock products' ?></span><span class="value"><?= $compact ? $money($cashMovements['totals']['cash_net'] ?? 0) : (int) ($lowStockCount ?? 0) ?></span></td>
</tr></table>
<?php if (abs($summary['other_adjustments']) >= .005): ?><p class="section-note">Other recorded adjustments: <?= $money($summary['other_adjustments']) ?>. Historical differences not recorded as VAT relief are shown separately.</p><?php endif; ?>
<p class="section-note">Sales use the sale date and all recorded refunds against those invoices. Revenue transactions exclude void and fully refunded invoices; return-related cost losses remain in profit. Sales-period costs reflect current return outcomes: resellable returns and released quarantine units recover product cost. Values retain their recorded VAT basis.</p>
<?= view('exports/cash_movements_pdf', ['cashMovements' => $cashMovements ?? null, 'compact' => $compact], ['saveData' => false]) ?>
<?php if ($compact): ?><p class="section-note">Use Full PDF for transaction details and sales forecasts.</p>
<?php else: ?>
<?php if ($hasSales || $hasCosts): ?>
<?php
$periods = array_map([ReportPdf::class, 'reconciliation'], $reportHistory ?? []);
$hasAdjustments = count(array_filter($periods, static fn($row) => abs($row['other_adjustments']) >= .005)) > 0;
$columns = [$col('Period', 13), $col('Txns', 5, true), $col('Original sales', 12, true), $col('Discounts', 10, true), $col('VAT relief', 10, true), $col('Refunds', 10, true), $col('Remaining sales', 12, true), $col('Cost', 12, true), $col('Recorded profit', 16, true)];
if ($hasAdjustments) $columns = [$col('Period', 11), $col('Txns', 5, true), $col('Original sales', 11, true), $col('Discounts', 9, true), $col('VAT relief', 9, true), $col('Refunds', 9, true), $col('Other adj.', 12, true), $col('Remaining', 11, true), $col('Cost', 10, true), $col('Profit', 13, true)];
$rows = [];
foreach ($periods as $row) {
    $cells = [DisplayDate::period($row['report_date'] ?? '-'), (string)($row['total_transactions'] ?? 0), $money($row['original_sales']), $money($row['total_discount'] ?? 0), $money($row['total_vat_relief']), $money($row['total_refunds'])];
    if ($hasAdjustments) $cells[] = $money($row['other_adjustments']);
    $rows[] = array_merge($cells, [$money($row['net_sales'] ?? 0), $money($row['cogs'] ?? 0), $money($row['gross_profit'] ?? 0)]);
}
echo $table('Sales and Profit by Period', $columns, $rows);
$rows = [];
foreach (($topProducts ?? []) as $row) $rows[] = [$row['product_name_snapshot'] ?? '-', (string)($row['total_qty_sold'] ?? 0), $money($row['total_sales'] ?? 0)];
echo $table('Top 10 Best-Selling Products', [$col('Product', 60), $col('Net units sold', 18, true), $col('Remaining product sales', 22, true)], $rows, 'Up to 10 products ranked by net units sold, after returns.');
$rows = [];
foreach (($recentSales ?? []) as $row) $rows[] = [$row['invoice_no'] ?? '-', $row['branch_name'] ?? '-', $row['full_name'] ?? '-', ucwords(str_replace('_', ' ', $row['status'] ?? '-')), $money($row['remaining_total'] ?? $row['final_total'] ?? 0), $money($row['refunded_amount'] ?? 0), DisplayDate::dateTime($row['sale_date'] ?? null)];
echo $table('Latest 10 Sales', [$col('Invoice', 19), $col('Branch', 13), $col('Cashier', 15), $col('Status', 12), $col('Remaining', 12, true), $col('Refunded', 12, true), $col('Sale date (PHT)', 17)], $rows, 'Up to 10 revenue transactions, newest invoice record first. This summary list is not the complete sales ledger.');
?>
<?php else: ?><p class="notice">No revenue transactions matched the sales filters. Zero-value detail tables are omitted.</p><?php endif; ?>
<?php if ($hasForecast): ?>
<?php
$rows = [];
foreach (($forecastHistory ?? []) as $row) $rows[] = [DisplayDate::period($row['report_date'] ?? '-'), $money($row['net_sales'] ?? 0), $money($row['forecast'] ?? 0)];
echo $table('Past Sales and Estimated Values', [$col('Period', 40), $col('Actual remaining sales', 30, true), $col('Estimated sales', 30, true)], $rows, 'Planning estimates based on the forecast history range; future sales are not guaranteed. Next estimate: ' . $money($nextForecastValue ?? 0));
$rows = [];
foreach (($futureForecast ?? []) as $row) $rows[] = [DisplayDate::period($row['forecast_date'] ?? '-'), $money($row['forecast_value'] ?? 0)];
echo $table('Estimated Future Sales', [$col('Forecast period', 50), $col('Estimated sales', 50, true)], $rows, !empty($forecastExcluded) ? 'Not counted because the period is not complete: ' . implode(', ', array_map([\App\Libraries\DisplayDate::class, 'period'], $forecastExcluded)) . '.' : '');
?>
<?php else: ?><p class="section-note">No recorded sales values are available for a useful forecast in the selected history range.</p><?php endif; ?>
<?php
$noun = ['daily' => 'day', 'weekly' => 'week', 'monthly' => 'month'][$forecastType ?? 'daily'] ?? 'day';
$future = \App\Libraries\ProductSalesForecast::FUTURE_PERIODS;
$trendLabels = ['up' => 'Rising', 'down' => 'Falling', 'steady' => 'Steady'];
$rows = [];
foreach (($productForecast ?? []) as $row) $rows[] = [$row['product_name'], number_format((int)$row['history_qty']), number_format((int)$row['next_qty']), $money($row['next_sales']), number_format((int)$row['total_qty']), $money($row['total_sales']), $trendLabels[$row['direction']] ?? 'Steady'];
echo $table('Product Sales Forecast', [$col('Product', 28), $col('Units sold', 10, true), $col("Next {$noun} units", 12, true), $col("Next {$noun} sales", 14, true), $col("Next {$future} {$noun}s units", 12, true), $col("Next {$future} {$noun}s sales", 15, true), $col('Trend', 9)], $rows, 'Top products by net units sold in the forecast history range, each forecast on its own, so the totals will not add up to the store-wide estimate. Net of returns, discounts and VAT relief. Planning estimates; future sales are not guaranteed.');
?>
<?php endif; ?>
</body></html>
