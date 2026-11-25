<?= $this->extend('layouts/staff') ?>
<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/reports.css') ?>?v=20261109-productfc3">

<?php
$forecastLabelText = 'Next day';
if (($forecastType ?? 'daily') === 'weekly') $forecastLabelText = 'Next week';
if (($forecastType ?? 'daily') === 'monthly') $forecastLabelText = 'Next month';

$today = date('Y-m-d');

$exportQuery = 'branch_id=' . urlencode($branchId ?? '')
    . '&report_type=' . urlencode($reportType ?? 'daily')
    . '&report_date_from=' . urlencode($reportDateFrom)
    . '&report_date_to=' . urlencode($reportDateTo)
    . '&forecast_date_from=' . urlencode($forecastDateFrom)
    . '&forecast_date_to=' . urlencode($forecastDateTo)
    . '&forecast_type=' . urlencode($forecastType ?? 'daily');

// ── Headline figures ──
$peso        = static fn ($value): string => '₱' . number_format((float) $value, 2);
$netSales    = (float) ($salesSummary['net_sales'] ?? 0);
$productCost = (float) ($profitSummary['cogs'] ?? 0);
$grossProfit = (float) ($profitSummary['gross_profit'] ?? 0);
[$netWhole, $netCents] = array_pad(explode('.', number_format($netSales, 2), 2), 2, '00');

// Where each peso of net sales went: product cost first, the rest is profit.
$costShare   = $netSales > 0 ? min(100.0, max(0.0, $productCost / $netSales * 100)) : 0.0;
$profitShare = $netSales > 0 ? min(100.0 - $costShare, max(0.0, $grossProfit / $netSales * 100)) : 0.0;

$topMaxQty = 0;
foreach (($topProducts ?? []) as $topRow) {
    $topMaxQty = max($topMaxQty, (int) ($topRow['total_qty_sold'] ?? 0));
}
$periodLabel   = \App\Libraries\DisplayDate::date($reportDateFrom) . ' – ' . \App\Libraries\DisplayDate::date($reportDateTo);
$forecastBasis = \App\Libraries\DisplayDate::date($forecastDateFrom) . ' – ' . \App\Libraries\DisplayDate::date($forecastDateTo);
?>

<div class="reports-workspace">

<!-- ── Header ── -->
<header class="rp-header">
    <div class="rp-title">
        <h1>Reports</h1>
        <p>Review sales, profit, best-selling products, and future sales estimates.</p>
    </div>
    <dl class="rp-scope" aria-label="What this page is showing">
        <div><dt>Branch</dt><dd><?= esc($branchName) ?></dd></div>
        <div><dt>Sales period</dt><dd><?= esc($periodLabel) ?></dd></div>
        <div><dt>Forecast uses</dt><dd><?= esc($forecastBasis) ?></dd></div>
    </dl>
</header>

<nav class="rp-section-nav" aria-label="Report sections">
    <span class="rp-nav-indicator" aria-hidden="true"></span>
    <a href="#reportFilters">Filters &amp; exports</a>
    <a href="#reportSummary">Overview</a>
    <a href="#reportTrends">Sales trends</a>
    <a href="#reportForecast">Forecast</a>
    <a href="#reportActivity">Sales details</a>
    <a href="#reportExportHistory">Export history</a>
</nav>

<?php if (!empty($filterNotices)): ?>
    <div class="rp-notices" role="status">
        <strong>Some filter values were adjusted:</strong>
        <ul>
            <?php foreach ($filterNotices as $notice): ?>
                <li><?= esc($notice) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- ── Filters ── -->
<section class="rp-panel rp-filters" id="reportFilters" aria-labelledby="standardReportsFilterTitle">
    <form method="get" action="<?= site_url('admin/reports') ?>" id="salesReportFilters">

        <input type="hidden" name="forecast_type" value="<?= esc($forecastType ?? 'daily') ?>">
        <input type="hidden" name="forecast_date_from" value="<?= esc($forecastDateFrom) ?>">
        <input type="hidden" name="forecast_date_to" value="<?= esc($forecastDateTo) ?>">

        <div class="rp-panel-head">
            <div>
                <h3 id="standardReportsFilterTitle">Sales report filters</h3>
                <p>Choose the branch, date range, and how the report should be grouped.</p>
            </div>
        </div>

        <div class="rp-panel-body">
            <div class="rp-filter-grid">
                <div class="field">
                    <label class="label" for="reportBranch">Branch</label>
                    <select name="branch_id" id="reportBranch" class="input">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= esc($b['id']) ?>" <?= ($branchId == $b['id']) ? 'selected' : '' ?>>
                                <?= esc($b['branch_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="label" for="reportGrouping">Group sales by</label>
                    <select name="report_type" id="reportGrouping" class="input">
                        <option value="daily" <?= ($reportType ?? 'daily') === 'daily' ? 'selected' : '' ?>>Daily</option>
                        <option value="weekly" <?= ($reportType ?? '') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                        <option value="monthly" <?= ($reportType ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                    </select>
                </div>
                <div class="field">
                    <label class="label" for="reportDateFrom">Start date</label>
                    <input type="date" id="reportDateFrom" name="report_date_from" class="input" value="<?= esc($reportDateFrom) ?>" max="<?= esc($today) ?>" required>
                </div>
                <div class="field">
                    <label class="label" for="reportDateTo">End date</label>
                    <input type="date" id="reportDateTo" name="report_date_to" class="input" value="<?= esc($reportDateTo) ?>" max="<?= esc($today) ?>" required>
                </div>
            </div>

            <div class="rp-date-presets" role="group" aria-label="Quick report date ranges" data-today="<?= esc($today) ?>">
                <button type="button" data-report-range="today" aria-pressed="false">Today</button>
                <button type="button" data-report-range="7" aria-pressed="false">Last 7 days</button>
                <button type="button" data-report-range="30" aria-pressed="false">Last 30 days</button>
                <button type="button" data-report-range="month" aria-pressed="false">This month</button>
            </div>
            <p class="rp-filter-help">These dates are used for sales totals, profit, product rankings, and transaction lists. Costs reflect current return outcomes, including quarantine releases, against the original sale period.</p>

            <div class="rp-actions-row">
                <div class="rp-submit-actions">
                    <button class="btn btn-primary" type="submit">Apply report filters</button>
                    <a href="<?= site_url('admin/reports') ?>" class="btn btn-secondary">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M3 12a9 9 0 1 0 2.64-6.36"/><polyline points="3 3 3 9 9 9"/>
                        </svg>
                        Reset
                    </a>
                </div>

                <div class="rp-export-actions" role="group" aria-label="Other reports and exports">
                    <a href="<?= site_url('admin/expiry-report') ?>" class="btn btn-secondary">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                        </svg>
                        Expiry Report
                    </a>
                    <a href="<?= site_url('admin/reports/export-excel?' . $exportQuery) ?>" class="btn btn-secondary" data-report-export>
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        Excel CSV
                    </a>
                    <a href="<?= site_url('admin/reports/pdf?' . $exportQuery) ?>" class="btn btn-secondary" data-report-export>
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
                        </svg>
                        Full PDF
                    </a>
                    <a href="<?= site_url('admin/reports/pdf?' . $exportQuery . '&pdf_mode=summary') ?>" class="btn btn-secondary" data-report-export>Summary PDF</a>
                </div>
            </div>
            <p class="rp-export-note">Exports use the applied report and forecast dates shown above.</p>
            <p class="rp-pending-filters" id="reportPendingFilters" role="status" hidden>Filters changed. Apply your report or forecast changes before exporting to include them.</p>
        </div>
    </form>
</section>

<!-- ── Overview ── -->
<div class="rp-section-heading" id="reportSummary"><h2>Performance overview</h2><p>Results for the applied sales period</p></div>

<div class="rp-overview">
    <section class="rp-panel rp-hero" aria-labelledby="rpHeroLabel">
        <div class="rp-hero-top">
            <div>
                <h3 class="rp-hero-label" id="rpHeroLabel">Net sales</h3>
                <p class="rp-hero-sub">Actual sales after discounts, VAT exemption and refunds</p>
            </div>
            <span class="rp-chip"><?= esc($periodLabel) ?></span>
        </div>

        <p class="rp-hero-figure">
            <span class="sr-only"><?= esc($peso($netSales)) ?></span>
            <span aria-hidden="true" data-count="<?= esc(number_format($netSales, 2, '.', ''), 'attr') ?>">
                <span class="rp-currency">₱</span><span class="rp-whole"><?= esc($netWhole) ?></span><span class="rp-cents">.<?= esc($netCents) ?></span>
            </span>
        </p>

        <?php if ($netSales > 0): ?>
            <div class="rp-split" role="img" aria-label="Of net sales, about <?= esc(round($costShare)) ?> percent covers product cost and <?= esc(round($profitShare)) ?> percent is gross profit">
                <span class="is-cost" style="width: <?= esc(number_format($costShare, 2, '.', ''), 'attr') ?>%"></span>
                <span class="is-profit" style="width: <?= esc(number_format($profitShare, 2, '.', ''), 'attr') ?>%"></span>
            </div>
        <?php endif; ?>

        <ul class="rp-split-legend">
            <li class="is-cost">
                <span class="rp-split-name"><i aria-hidden="true"></i>Product cost</span>
                <strong><?= esc($peso($productCost)) ?></strong>
                <small>Cost of the products that were sold</small>
            </li>
            <li class="is-profit">
                <span class="rp-split-name"><i aria-hidden="true"></i>Gross profit</span>
                <strong><?= esc($peso($grossProfit)) ?></strong>
                <small><?= number_format((float) ($grossProfitMargin ?? 0), 1) ?>% of net sales</small>
            </li>
        </ul>
    </section>

    <aside class="rp-panel rp-ahead" aria-label="Looking ahead">
        <div class="rp-ahead-item">
            <h3 class="rp-metric-label"><?= esc($forecastLabelText) ?> forecast</h3>
            <p class="rp-metric-value rp-forecast-value"><?= esc($peso($nextForecastValue ?? 0)) ?></p>
            <p class="rp-metric-sub">Estimated from the selected sales history</p>
        </div>
        <div class="rp-ahead-item">
            <h3 class="rp-metric-label">Low-stock products</h3>
            <p class="rp-metric-value"><?= esc($lowStockCount ?? 0) ?></p>
            <p class="rp-metric-sub">At or below the recorded reorder level</p>
            <a class="rp-linkbtn" href="<?= site_url('products' . (!empty($branchId) ? '?branch_id=' . (int) $branchId : '')) ?>">Open Products</a>
        </div>
    </aside>

    <?= view('admin/reports/_sales_summary', ['summary' => $salesSummary ?? [], 'averageTransactionValue' => $averageTransactionValue ?? 0], ['saveData' => false]) ?>
</div>

<?= view('admin/reports/cash_movements', ['cashMovements' => $cashMovements ?? null]) ?>

<!-- ── Sales trends ── -->
<div class="rp-section-heading" id="reportTrends"><h2>Sales trends</h2><p>Track earnings and the products customers buy most</p></div>
<div class="rp-trends">

    <section class="rp-panel">
        <div class="rp-panel-head">
            <div>
                <h3>Sales and profit</h3>
                <p>Compare sales, product cost, and profit over time</p>
            </div>
        </div>
        <div class="rp-panel-body">
            <div class="chart-wrap"><canvas id="compositionChart" role="img" aria-label="Net sales, product costs and gross profit by period"></canvas></div>
        </div>
    </section>

    <section class="rp-panel">
        <div class="rp-panel-head">
            <div>
                <h3>Best-selling products</h3>
                <p>By quantity sold in this period</p>
            </div>
        </div>
        <div class="rp-panel-body">
            <div class="chart-wrap"><canvas id="topProductsChart" role="img" aria-label="Best-selling products by quantity; values are listed in Sales details below"></canvas></div>
        </div>
    </section>

</div>

<!-- ── Forecast ── -->
<div class="rp-section-heading" id="reportForecast"><h2>Plan ahead</h2><p>Forecast sales from recorded history</p></div>

<?php if ($message = session()->getFlashdata('forecast_success')): ?>
    <div class="rp-notices is-success" role="status"><?= esc($message) ?></div>
<?php endif; ?>
<?php if ($message = session()->getFlashdata('forecast_error')): ?>
    <div class="rp-notices is-error" role="alert"><?= esc($message) ?></div>
<?php endif; ?>

<section class="rp-panel rp-filters rp-forecast-filters" aria-labelledby="forecastFilterTitle">
    <form method="post" action="<?= site_url('admin/reports/update-forecast') ?>" data-return-url="<?= site_url('admin/reports') ?>" id="salesForecastFilters" data-auto-filter="off">
        <?= csrf_field() ?>
        <input type="hidden" name="branch_id" value="<?= esc($branchId ?? '') ?>">
        <input type="hidden" name="report_type" value="<?= esc($reportType ?? 'daily') ?>">
        <input type="hidden" name="report_date_from" value="<?= esc($reportDateFrom) ?>">
        <input type="hidden" name="report_date_to" value="<?= esc($reportDateTo) ?>">

        <div class="rp-panel-head">
            <div>
                <h3 id="forecastFilterTitle">Sales forecast settings</h3>
                <p>Choose the past sales period that the system should use for the estimate. Maximum: 24 months.</p>
            </div>
        </div>

        <div class="rp-panel-body">
            <div class="rp-forecast-grid">
                <div class="field">
                    <label class="label" for="forecastGrouping">Show forecast by</label>
                    <select name="forecast_type" id="forecastGrouping" class="input">
                        <option value="daily" <?= ($forecastType ?? 'daily') === 'daily' ? 'selected' : '' ?>>Daily</option>
                        <option value="weekly" <?= ($forecastType ?? '') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                        <option value="monthly" <?= ($forecastType ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                    </select>
                </div>
                <div class="field">
                    <label class="label" for="forecastDateFrom">Use sales from</label>
                    <input type="date" id="forecastDateFrom" name="forecast_date_from" class="input" value="<?= esc($forecastDateFrom) ?>" max="<?= esc($today) ?>" required>
                </div>
                <div class="field">
                    <label class="label" for="forecastDateTo">Use sales to</label>
                    <input type="date" id="forecastDateTo" name="forecast_date_to" class="input" value="<?= esc($forecastDateTo) ?>" max="<?= esc($today) ?>" required>
                </div>
                <button class="btn btn-primary" type="submit">Update Forecast</button>
            </div>
            <p class="rp-filter-help">Click Update Forecast to apply these filters and save the seven sales forecast periods shown below. Matching records from today are refreshed.</p>
            <p class="rp-pending-filters" id="forecastPendingFilters" role="status" hidden>Forecast changes are not applied yet. Click Update Forecast to refresh the results.</p>
        </div>
    </form>
</section>

<section class="rp-panel rp-forecast-chart">
    <div class="rp-panel-head">
        <div>
            <h3>Sales forecast</h3>
            <p>Past sales compared with estimated future sales. Update Forecast saves the seven future values with their target dates.</p>
        </div>
        <span class="rp-chip"><?= esc(ucfirst($forecastType ?? 'daily')) ?> view</span>
    </div>
    <div class="rp-panel-body">
        <div class="rp-chart-actions">
            <button type="button" class="btn btn-secondary" id="savedSalesForecastTrigger" aria-controls="forecastModalOverlay" aria-expanded="false">Saved sales forecasts</button>
        </div>
        <div class="chart-wrap is-tall"><canvas id="forecastChart" role="img" aria-label="Actual and estimated sales; forecast values are listed in the tables below"></canvas></div>
    </div>
    <?php if (!empty($forecastExcluded)): ?>
        <p class="rp-note">Not counted in the estimate because the period is not complete yet or is only partly inside the dates: <?= esc(implode(', ', array_map([\App\Libraries\DisplayDate::class, 'period'], $forecastExcluded))) ?>. It still shows in the chart.</p>
    <?php endif; ?>
    <p class="rp-note">This forecast is an estimate based on past sales. Use it as a guide for sales planning.</p>
</section>

<?php
$productPeriodNoun = ['daily' => 'day', 'weekly' => 'week', 'monthly' => 'month'][$forecastType ?? 'daily'] ?? 'day';
$productFutureCount = \App\Libraries\ProductSalesForecast::FUTURE_PERIODS;
$productTrendBadges = [
    'up'     => ['badge-success', 'Rising'],
    'down'   => ['badge-danger', 'Falling'],
    'steady' => ['badge-neutral', 'Steady'],
];
?>
<section class="rp-panel rp-product-forecast" id="productSalesForecast" aria-labelledby="productSalesForecastTitle">
    <div class="rp-panel-head">
        <div>
            <h3 id="productSalesForecastTitle">Product sales forecast</h3>
            <p>Estimated units and sales for your top <?= (int) \App\Libraries\ProductSalesForecast::DEFAULT_LIMIT ?> products over the next <?= $productFutureCount ?> <?= esc($productPeriodNoun) ?>s, using the same sales history and settings as the forecast above.</p>
        </div>
        <div class="rp-head-actions">
            <span class="rp-chip"><?= esc(ucfirst($forecastType ?? 'daily')) ?> view</span>
            <button type="button" class="btn btn-secondary" id="productForecastTrigger" data-url="<?= esc(site_url('admin/reports/product-forecast?' . $exportQuery), 'attr') ?>" aria-controls="productForecastModalOverlay" aria-haspopup="dialog">View all products</button>
        </div>
    </div>
    <div class="table-wrap table-scroll">
        <table class="mobile-record-table rp-table js-datatable" data-dt-mode="simple">
            <thead>
                <tr>
                    <th>Product</th>
                    <th class="num">Units sold</th>
                    <th class="num">Next <?= esc($productPeriodNoun) ?> units</th>
                    <th class="num">Next <?= esc($productPeriodNoun) ?> sales</th>
                    <th class="num">Next <?= $productFutureCount ?> <?= esc($productPeriodNoun) ?>s units</th>
                    <th class="num">Next <?= $productFutureCount ?> <?= esc($productPeriodNoun) ?>s sales</th>
                    <th>Trend</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($productForecast)): ?>
                    <?php foreach ($productForecast as $item): ?>
                        <?php [$trendClass, $trendLabel] = $productTrendBadges[$item['direction']] ?? $productTrendBadges['steady']; ?>
                        <tr>
                            <td><span class="rp-rank-name"><?= esc($item['product_name']) ?></span></td>
                            <td class="num"><?= number_format((int) $item['history_qty']) ?></td>
                            <td class="num"><?= number_format((int) $item['next_qty']) ?></td>
                            <td class="num amount rp-forecast-value">₱<?= number_format((float) $item['next_sales'], 2) ?></td>
                            <td class="num"><?= number_format((int) $item['total_qty']) ?></td>
                            <td class="num amount rp-forecast-value">₱<?= number_format((float) $item['total_sales'], 2) ?></td>
                            <td><span class="badge <?= esc($trendClass, 'attr') ?>"><?= esc($trendLabel) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="rp-empty">No product sales found in the forecast history range.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="rp-note">Each product is forecast on its own, so these figures will not add up to the store-wide sales forecast, which also includes products outside the top <?= (int) \App\Libraries\ProductSalesForecast::DEFAULT_LIMIT ?>. Units and sales are net of returns. Use them as a guide for sales planning; future sales are not guaranteed.</p>
</section>
<!-- Saved sales forecasts -->
<div class="forecast-modal-overlay" id="forecastModalOverlay" aria-hidden="true">
    <div class="forecast-modal" role="dialog" aria-modal="true" aria-labelledby="forecastModalTitle" aria-describedby="salesForecastContext">
        <div class="forecast-modal-head">
            <div><h2 id="forecastModalTitle">Saved Sales Forecasts</h2><p id="salesForecastContext">Latest 100 sales forecast periods across all branches, newest first.</p></div>
            <button type="button" class="forecast-modal-close" id="forecastModalClose" aria-label="Close saved sales forecasts">×</button>
        </div>
        <div class="forecast-modal-body">
            <?php $forecastLog = $forecastingDataLog ?? []; ?>
            <?php if ($forecastLog): ?>
            <div class="table-wrap table-scroll"><table class="forecast-log-table js-datatable" data-dt-length="25">
                <thead><tr><th>ID</th><th>Target period</th><th>Sales history used</th><th>Branch</th><th>Estimated sales</th><th>Generated at</th></tr></thead>
                <tbody>
                <?php foreach ($forecastLog as $row): ?>
                    <?php
                    $hasBasis = preg_match('/^Sales Holt b=(all|\d+) (\d{4}-\d{2}-\d{2})\.\.(\d{4}-\d{2}-\d{2}) (daily|weekly|monthly) /', (string) ($row['method_used'] ?? ''), $basis);
                    $periodLabel = $hasBasis ? match ($basis[4]) {
                        'weekly' => 'Week of ' . \App\Libraries\DisplayDate::date($row['forecast_month']),
                        'monthly' => \App\Libraries\DisplayDate::month($row['forecast_month']),
                        default => \App\Libraries\DisplayDate::date($row['forecast_month']),
                    } : \App\Libraries\DisplayDate::date($row['forecast_month']);
                    ?>
                    <tr>
                        <td><?= esc($row['id']) ?></td><td><?= esc($periodLabel) ?></td>
                        <td><?= $hasBasis ? esc(\App\Libraries\DisplayDate::date($basis[2]) . ' to ' . \App\Libraries\DisplayDate::date($basis[3])) : 'Not recorded' ?></td>
                        <td><?= $hasBasis ? ($basis[1] === 'all' ? 'All branches' : 'Branch #' . esc($basis[1])) : 'Not recorded' ?></td>
                        <td class="amount">₱<?= number_format((float) ($row['predicted_revenue'] ?? 0), 2) ?></td>
                        <td><?= esc(\App\Libraries\DisplayDate::dateTime($row['generated_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php else: ?><p class="muted">No saved sales forecasts yet. Update Forecast saves seven sales forecast periods.</p><?php endif; ?>
            <p class="rp-note">Saved values match the sales forecast chart for their target periods. Updating the same filters today refreshes matching records.</p>
        </div>
        <div class="restock-footer"><button type="button" class="btn" id="forecastModalDone">Close</button></div>
    </div>
</div>

<!-- All product sales forecasts -->
<div class="forecast-modal-overlay" id="productForecastModalOverlay" aria-hidden="true">
    <div class="forecast-modal" role="dialog" aria-modal="true" aria-labelledby="productForecastModalTitle" aria-describedby="productForecastContext">
        <div class="forecast-modal-head">
            <div>
                <h2 id="productForecastModalTitle">All product sales forecasts</h2>
                <p class="forecast-modal-sub" id="productForecastContext"><?= esc($branchName) ?> · Sales from <?= esc($forecastBasis) ?> · <?= esc(ucfirst($forecastType ?? 'daily')) ?> view</p>
            </div>
            <button type="button" class="forecast-modal-close" id="productForecastModalClose" aria-label="Close all product sales forecasts">×</button>
        </div>
        <div class="forecast-modal-body">
            <p class="rp-product-status" id="productForecastStatus" role="status">Loading products…</p>
            <section class="rp-panel rp-product-forecast rp-product-forecast-all">
                <div class="table-wrap">
                    <table class="mobile-record-table rp-table js-datatable" id="productForecastTable" data-dt-live data-dt-length="25" data-dt-empty="No product sales found in the forecast history range.">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="num">Units sold</th>
                                <th class="num">Next <?= esc($productPeriodNoun) ?> units</th>
                                <th class="num">Next <?= esc($productPeriodNoun) ?> sales</th>
                                <th class="num">Next <?= $productFutureCount ?> <?= esc($productPeriodNoun) ?>s units</th>
                                <th class="num">Next <?= $productFutureCount ?> <?= esc($productPeriodNoun) ?>s sales</th>
                                <th>Trend</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <p class="rp-note">Products are ranked by net units sold, and only products with sales in the forecast history range are listed. Each product is forecast on its own, so the figures will not add up to the store-wide sales forecast. Units and sales are net of returns. Use them as a guide for sales planning; future sales are not guaranteed.</p>
            </section>
        </div>
        <div class="restock-footer">
            <a class="btn btn-secondary" id="productForecastExport" href="<?= esc(site_url('admin/reports/product-forecast/export-excel?' . $exportQuery), 'attr') ?>">Excel CSV</a>
            <button type="button" class="btn" id="productForecastModalDone">Close</button>
        </div>
    </div>
</div>

<!-- ── Sales details ── -->
<div class="rp-section-heading" id="reportActivity"><h2>Sales details</h2><p>Product rankings, recent transactions and forecast values</p></div>
<div class="rp-two-col">

    <section class="rp-panel">
        <div class="rp-panel-head">
            <div>
                <h3>Best-selling products</h3>
                <p>Showing up to 10 top-selling products for the selected filters.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="mobile-record-table rp-table js-datatable" data-dt-mode="simple">
                <thead>
                    <tr><th>Product</th><th class="num">Units sold</th><th class="num">Sales</th></tr>
                </thead>
                <tbody>
                    <?php if (!empty($topProducts)): ?>
                        <?php foreach ($topProducts as $item): ?>
                            <?php $share = $topMaxQty > 0 ? round(((int) $item['total_qty_sold']) / $topMaxQty * 100, 1) : 0; ?>
                            <tr>
                                <td>
                                    <span class="rp-rank-name"><?= esc($item['product_name_snapshot']) ?></span>
                                    <span class="rp-rank-bar" aria-hidden="true" style="--w: <?= esc($share, 'attr') ?>%"></span>
                                </td>
                                <td class="num"><?= esc($item['total_qty_sold']) ?></td>
                                <td class="num amount">₱<?= number_format((float) $item['total_sales'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="rp-empty">No sales data found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="rp-panel">
        <div class="rp-panel-head">
            <div>
                <h3>Recent sales</h3>
                <p>Showing up to 10 most recent sales for the selected filters.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="mobile-record-table rp-table js-datatable" data-dt-mode="simple">
                <thead>
                    <tr><th>Invoice</th><th>Status</th><th class="num">Remaining total</th><th>Date</th></tr>
                </thead>
                <tbody>
                    <?php if (!empty($recentSales)): ?>
                        <?php foreach ($recentSales as $sale): ?>
                            <tr>
                                <td class="rp-nowrap"><?= esc($sale['invoice_no']) ?></td>
                                <td>
                                    <span class="badge <?= ($sale['status'] ?? '') === 'partially_refunded' ? 'badge-warning' : 'badge-success' ?>">
                                        <?= ($sale['status'] ?? '') === 'partially_refunded' ? 'Partial Refund' : 'Completed' ?>
                                    </span>
                                </td>
                                <td class="num amount">₱<?= number_format((float) ($sale['remaining_total'] ?? $sale['final_total']), 2) ?></td>
                                <td><?= esc(\App\Libraries\DisplayDate::dateTime($sale['sale_date'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="rp-empty">No recent sales found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>

<div class="rp-two-col">

    <section class="rp-panel">
        <div class="rp-panel-head">
            <div>
                <h3>Forecast history</h3>
                <p>Fitted values against actual net sales</p>
            </div>
        </div>
        <div class="table-wrap table-scroll">
            <table class="mobile-record-table rp-table js-datatable" data-dt-length="10">
                <thead>
                    <tr><th>Period</th><th class="num">Net sales</th><th class="num">Fitted forecast</th></tr>
                </thead>
                <tbody>
                    <?php if (!empty($forecastHistory)): ?>
                        <?php foreach ($forecastHistory as $row): ?>
                            <tr>
                                <td><?= esc(\App\Libraries\DisplayDate::period($row['report_date'])) ?></td>
                                <td class="num amount">₱<?= number_format((float) ($row['net_sales'] ?? 0), 2) ?></td>
                                <td class="num">₱<?= number_format((float) ($row['forecast'] ?? 0), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="rp-empty">No forecast data found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="rp-panel">
        <div class="rp-panel-head">
            <div>
                <h3>Future forecast</h3>
                <p>
                    <?php if (($forecastType ?? 'daily') === 'weekly'): ?>Next 7 weeks
                    <?php elseif (($forecastType ?? 'daily') === 'monthly'): ?>Next 7 months
                    <?php else: ?>Next 7 days
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <div class="table-wrap table-scroll">
            <table class="mobile-record-table rp-table js-datatable" data-dt-mode="simple">
                <thead>
                    <tr><th>Forecast period</th><th class="num">Forecast sales</th></tr>
                </thead>
                <tbody>
                    <?php if (!empty($futureForecast)): ?>
                        <?php foreach ($futureForecast as $row): ?>
                            <tr>
                                <td><?= esc(\App\Libraries\DisplayDate::period($row['forecast_date'])) ?></td>
                                <td class="num amount rp-forecast-value">₱<?= number_format((float) ($row['forecast_value'] ?? 0), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="2" class="rp-empty">No future forecast found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>

<!-- ── Export history ── -->
<div class="rp-section-heading" id="reportExportHistory"><h2>Export history</h2><p>Last 10 exports<?= !empty($branchId) ? ' for this branch' : '' ?></p></div>
<section class="rp-panel">
    <div class="table-wrap">
        <table class="mobile-record-table rp-table js-datatable" data-dt-mode="simple">
            <thead>
                <tr><th>Generated</th><th>User</th><th>Branch</th><th>Type</th><th>Report period</th><th class="num">Gross</th><th class="num">Profit</th></tr>
            </thead>
            <tbody>
                <?php if (!empty($exportHistory)): ?>
                    <?php foreach ($exportHistory as $row): ?>
                        <tr>
                            <td><?= \App\Libraries\DisplayDate::date($row['created_at']) ?><br><span class="muted"><?= \App\Libraries\DisplayDate::time($row['created_at']) ?></span></td>
                            <td><?= esc($row['full_name'] ?? '-') ?></td>
                            <td><?= esc($row['branch_name'] ?? 'All Branches') ?></td>
                            <td>
                                <span class="badge <?= $row['export_type'] === 'pdf' ? 'badge-danger' : 'badge-success' ?>">
                                    <?= strtoupper(esc($row['export_type'])) ?>
                                </span>
                            </td>
                            <td><?= esc(\App\Libraries\DisplayDate::date($row['date_from'])) ?> – <?= esc(\App\Libraries\DisplayDate::date($row['date_to'])) ?></td>
                            <td class="num amount">₱<?= number_format((float) $row['gross_sales'], 2) ?></td>
                            <td class="num amount">₱<?= number_format((float) $row['gross_profit'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="rp-empty">No export history found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

</div>
<script src="<?= base_url('assets/js/reports.js') ?>?v=20261028-no-reorder" defer></script>
<script src="<?= base_url('assets/js/report-exports.js') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
/* ── Charts ──
   Each chart is drawn the first time it scrolls into view, with a left-to-right
   reveal. Colours are read from the page's CSS tokens, so the accent colour and
   dark mode chosen in Interface settings carry through. */
(function () {
    if (typeof Chart === 'undefined') {
        document.querySelectorAll('.chart-wrap').forEach(function (wrap) {
            wrap.innerHTML = '<p class="rp-chart-empty">Charts could not be loaded. The report tables and exports are still available.</p>';
        });
        return;
    }

    const reportLabels = <?= json_encode(array_map([\App\Libraries\DisplayDate::class, 'period'], array_column($reportHistory, 'report_date'))) ?>;
    const reportNetSales = <?= json_encode(array_map('floatval', array_column($reportHistory, 'net_sales'))) ?>;
    const reportCogs = <?= json_encode(array_map('floatval', array_column($reportHistory, 'cogs'))) ?>;
    const reportGrossProfit = <?= json_encode(array_map('floatval', array_column($reportHistory, 'gross_profit'))) ?>;

    const forecastLabels = <?= json_encode(array_map([\App\Libraries\DisplayDate::class, 'period'], array_column($forecastHistory, 'report_date'))) ?>;
    const forecastActualSales = <?= json_encode(array_map('floatval', array_column($forecastHistory, 'net_sales'))) ?>;
    const fittedForecast = <?= json_encode(array_map('floatval', array_column($forecastHistory, 'forecast'))) ?>;

    const futureLabels = <?= json_encode(array_map([\App\Libraries\DisplayDate::class, 'period'], array_column($futureForecast, 'forecast_date'))) ?>;
    const futureValues = <?= json_encode(array_map('floatval', array_column($futureForecast, 'forecast_value'))) ?>;

    const topLabels = <?= json_encode(array_column($topProducts, 'product_name_snapshot')) ?>;
    const topQty = <?= json_encode(array_map('intval', array_column($topProducts, 'total_qty_sold'))) ?>;

    // ── Theme: read the page's own tokens ──
    const root = document.querySelector('.reports-workspace');
    const html = document.documentElement;
    const still = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches || html.classList.contains('reduce-motion');
    const token = name => getComputedStyle(root).getPropertyValue(name).trim();
    const theme = () => ({
        sales: token('--rp-sales') || '#3452f4',
        cost: token('--rp-cost') || '#c77a0a',
        profit: token('--rp-profit') || '#138a52',
        forecast: token('--rp-forecast') || '#6d4df2',
        text: token('--text') || '#0f1419',
        text2: token('--text-2') || '#313a48',
        muted: token('--muted') || '#5d6879',
        line: token('--line') || '#e1e4e9',
        surface: token('--surface') || '#ffffff',
        font: getComputedStyle(document.body).fontFamily
    });

    const alpha = (color, a) => {
        const c = String(color).trim();
        let m = /^#([0-9a-f]{3})$/i.exec(c);
        if (m) m = [null, m[1].split('').map(ch => ch + ch).join('')];
        else m = /^#([0-9a-f]{6})$/i.exec(c);
        if (m) {
            const n = parseInt(m[1], 16);
            return 'rgba(' + (n >> 16 & 255) + ',' + (n >> 8 & 255) + ',' + (n & 255) + ',' + a + ')';
        }
        const rgb = /^rgba?\(([^)]+)\)/i.exec(c);
        return rgb ? 'rgba(' + rgb[1].split(',').slice(0, 3).map(s => s.trim()).join(',') + ',' + a + ')' : c;
    };

    const peso = v => '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const pesoShort = v => {
        const a = Math.abs(v);
        const trim = n => String(n).replace(/\.0$/, '');
        if (a >= 1e6) return '₱' + trim((v / 1e6).toFixed(a >= 1e7 ? 0 : 1)) + 'M';
        if (a >= 1e3) return '₱' + trim((v / 1e3).toFixed(a >= 1e4 ? 0 : 1)) + 'k';
        return '₱' + v;
    };

    // ── Plugins ──
    // Reveal: the chart is drawn through a clip that wipes in from the left.
    let rebuilding = false;
    const reveal = {
        id: 'rpReveal',
        beforeInit(chart) { chart.$rp = (rebuilding || still()) ? 1 : 0; },
        beforeDatasetsDraw(chart) {
            if (chart.$rp >= 1) return;
            const c = chart.ctx;
            c.save();
            c.beginPath();
            c.rect(0, 0, chart.chartArea.left + (chart.width - chart.chartArea.left) * chart.$rp, chart.height);
            c.clip();
            chart.$rpClip = true;
        },
        afterDatasetsDraw(chart) {
            if (chart.$rpClip) { chart.ctx.restore(); chart.$rpClip = false; }
        }
    };
    const play = chart => {
        if (chart.$rp >= 1) return;
        const start = performance.now(), duration = 1100;
        const tick = now => {
            if (!chart.ctx) return;
            const k = Math.min(1, (now - start) / duration);
            chart.$rp = 1 - Math.pow(1 - k, 3);
            chart.draw();
            if (k < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    };

    // Crosshair: a hairline under the pointer on line charts.
    const crosshair = {
        id: 'rpCrosshair',
        afterDatasetsDraw(chart) {
            const active = chart.tooltip && chart.tooltip.getActiveElements ? chart.tooltip.getActiveElements() : [];
            if (!active.length || chart.$rp < 1) return;
            const x = active[0].element.x, a = chart.chartArea, c = chart.ctx;
            c.save();
            c.strokeStyle = chart.$rpLine;
            c.lineWidth = 1;
            c.beginPath();
            c.moveTo(x, a.top);
            c.lineTo(x, a.bottom);
            c.stroke();
            c.restore();
        }
    };

    const common = t => ({
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        interaction: { mode: 'index', intersect: false },
        layout: { padding: { top: 4, right: 8 } },
        font: { family: t.font },
        plugins: {
            legend: {
                align: 'start',
                labels: { color: t.text2, usePointStyle: true, pointStyle: 'circle', boxWidth: 7, boxHeight: 7, padding: 18, font: { size: 12, weight: '500' } }
            },
            tooltip: {
                backgroundColor: t.surface, titleColor: t.text, bodyColor: t.text2,
                borderColor: t.line, borderWidth: 1, padding: 12, cornerRadius: 10, boxPadding: 5,
                usePointStyle: true, caretSize: 0,
                titleFont: { size: 12, weight: '600' }, bodyFont: { size: 12 },
                callbacks: { label: ctx => ' ' + ctx.dataset.label + ': ' + peso(ctx.parsed.y) }
            }
        }
    });

    const axes = t => ({
        x: { grid: { display: false }, border: { color: t.line }, ticks: { color: t.muted, font: { size: 11 }, maxTicksLimit: 8, maxRotation: 0 } },
        y: { beginAtZero: true, grid: { color: alpha(t.line, .9) }, border: { display: false }, ticks: { color: t.muted, font: { size: 11 }, padding: 8, callback: pesoShort } }
    });

    const area = (color, strength) => ctx => {
        const a = ctx.chart.chartArea;
        if (!a) return 'transparent';
        const g = ctx.chart.ctx.createLinearGradient(0, a.top, 0, a.bottom);
        g.addColorStop(0, alpha(color, strength));
        g.addColorStop(1, alpha(color, 0));
        return g;
    };
    const line = (color, count, extra) => Object.assign({
        borderColor: color, borderWidth: 2.25, cubicInterpolationMode: 'monotone',
        pointRadius: count <= 2 ? 4 : 0, pointHoverRadius: 5, pointHoverBorderWidth: 2,
        pointBackgroundColor: color, pointHoverBackgroundColor: '#fff', pointHoverBorderColor: color
    }, extra || {});

    // ── Chart definitions ──
    const defs = [
        {
            id: 'compositionChart', count: reportLabels.length,
            build: t => ({
                type: 'line',
                data: {
                    labels: reportLabels,
                    datasets: [
                        line(t.sales, reportLabels.length, { label: 'Net Sales', data: reportNetSales, fill: true, backgroundColor: area(t.sales, .18) }),
                        line(t.cost, reportLabels.length, { label: 'Product Cost', data: reportCogs, borderDash: [5, 4], borderWidth: 1.75 }),
                        line(t.profit, reportLabels.length, { label: 'Gross Profit', data: reportGrossProfit })
                    ]
                },
                options: Object.assign(common(t), { scales: axes(t) }),
                plugins: [reveal, crosshair]
            })
        },
        {
            id: 'topProductsChart', count: topLabels.length,
            build: t => {
                const o = common(t);
                o.indexAxis = 'y';
                o.interaction = { mode: 'nearest', axis: 'y', intersect: false };
                o.layout = { padding: { top: 2, right: 34 } };
                o.plugins.legend = { display: false };
                o.plugins.tooltip.callbacks = { label: ctx => ' ' + ctx.parsed.x + ' units' };
                o.scales = {
                    x: { beginAtZero: true, grid: { color: alpha(t.line, .9) }, border: { display: false }, ticks: { color: t.muted, font: { size: 11 }, precision: 0 } },
                    y: { grid: { display: false }, border: { display: false }, ticks: { color: t.text2, font: { size: 12 }, callback: function (value) { const label = this.getLabelForValue(value); return label.length > 24 ? label.slice(0, 23) + '…' : label; } } }
                };
                return {
                    type: 'bar',
                    data: {
                        labels: topLabels,
                        datasets: [{
                            label: 'Qty Sold', data: topQty,
                            backgroundColor: topQty.map((_, i) => alpha(t.sales, Math.max(.38, 1 - i * .075))),
                            borderRadius: 6, borderSkipped: false, maxBarThickness: 22
                        }]
                    },
                    options: o,
                    plugins: [reveal, {
                        // Quantity at the end of each bar, so the chart reads without hovering.
                        id: 'rpBarLabels',
                        afterDatasetsDraw(chart) {
                            if (chart.$rp < .9) return;
                            const c = chart.ctx;
                            c.save();
                            c.fillStyle = t.text2;
                            c.font = '600 11px ' + t.font;
                            c.textBaseline = 'middle';
                            chart.getDatasetMeta(0).data.forEach((bar, i) => c.fillText(String(topQty[i]), bar.x + 8, bar.y));
                            c.restore();
                        }
                    }]
                };
            }
        },
        {
            id: 'forecastChart', count: forecastLabels.length + futureLabels.length,
            build: t => {
                const pastCount = forecastLabels.length;
                const total = pastCount + futureLabels.length;
                return {
                    type: 'line',
                    data: {
                        labels: forecastLabels.concat(futureLabels),
                        datasets: [
                            line(t.sales, total, { label: 'Actual Sales', data: forecastActualSales.concat(Array(futureLabels.length).fill(null)), fill: true, backgroundColor: area(t.sales, .16) }),
                            line(t.forecast, total, {
                                label: 'Estimated Sales', data: fittedForecast.concat(futureValues), borderDash: [6, 5],
                                pointRadius: ctx => total <= 2 || ctx.dataIndex >= pastCount ? 3.5 : 0
                            })
                        ]
                    },
                    options: Object.assign(common(t), { scales: axes(t) }),
                    plugins: [{
                        // Shades the stretch of the chart that is estimated rather than recorded.
                        id: 'rpFuture',
                        beforeDatasetsDraw(chart) {
                            if (!futureLabels.length || !pastCount) return;
                            const a = chart.chartArea, c = chart.ctx;
                            const x0 = Math.max(a.left, chart.scales.x.getPixelForValue(pastCount - 1));
                            c.save();
                            c.fillStyle = alpha(t.forecast, .07);
                            c.fillRect(x0, a.top, a.right - x0, a.bottom - a.top);
                            c.fillStyle = t.forecast;
                            c.font = '600 11px ' + t.font;
                            c.textAlign = 'right';
                            c.fillText('Estimated', a.right - 10, a.top + 18);
                            c.restore();
                        }
                    }, reveal, crosshair]
                };
            }
        }
    ];

    const live = new Map();
    const mount = def => {
        const canvas = document.getElementById(def.id);
        if (!canvas) return;
        if (live.has(def.id)) live.get(def.id).destroy();
        const t = theme();
        const chart = new Chart(canvas, def.build(t));
        chart.$rpLine = alpha(t.muted, .45);
        live.set(def.id, chart);
        return chart;
    };

    defs.forEach(def => {
        const canvas = document.getElementById(def.id);
        if (!canvas) return;
        const wrap = canvas.parentElement;
        if (!def.count) {
            canvas.hidden = true;
            const message = document.createElement('p');
            message.className = 'rp-chart-empty';
            message.textContent = 'No data for this chart. Try a different date range or branch.';
            wrap.appendChild(message);
            return;
        }
        const draw = () => { const chart = mount(def); if (chart) play(chart); };
        if ('IntersectionObserver' in window) {
            const io = new IntersectionObserver(entries => {
                if (!entries.some(entry => entry.isIntersecting)) return;
                io.disconnect();
                draw();
            }, { threshold: .25 });
            io.observe(wrap);
        } else {
            draw();
        }
    });

    // ── Re-theme when dark mode or the accent colour changes ──
    let pending = 0;
    new MutationObserver(() => {
        window.clearTimeout(pending);
        pending = window.setTimeout(() => {
            rebuilding = true;
            defs.forEach(def => { if (live.has(def.id)) mount(def); });
            rebuilding = false;
        }, 60);
    }).observe(html, { attributes: true, attributeFilter: ['class', 'data-accent'] });
})();

(function () {
    var trigger = document.getElementById('savedSalesForecastTrigger');
    var overlay = document.getElementById('forecastModalOverlay');
    var closeBtn = document.getElementById('forecastModalClose');
    if (!trigger || !overlay || !closeBtn) return;

    function open() {
        window.PharxmacoModal.open(overlay, {trigger: trigger, initialFocus: closeBtn});
    }
    function close() {
        window.PharxmacoModal.close(overlay);
    }

    trigger.addEventListener('click', open);
    closeBtn.addEventListener('click', close);
    document.getElementById('forecastModalDone').addEventListener('click', close);
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) close();
    });
})();

(function () {
    var trigger = document.getElementById('productForecastTrigger');
    var overlay = document.getElementById('productForecastModalOverlay');
    var closeBtn = document.getElementById('productForecastModalClose');
    var doneBtn = document.getElementById('productForecastModalDone');
    var table = document.getElementById('productForecastTable');
    var status = document.getElementById('productForecastStatus');
    if (!trigger || !overlay || !closeBtn || !doneBtn || !table || !status) return;

    var loaded = false;
    var loading = false;

    function load() {
        if (loaded || loading) return;
        loading = true;
        status.hidden = false;
        status.textContent = 'Loading products…';
        fetch(trigger.dataset.url, {
            credentials: 'same-origin',
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
        }).then(function (response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        }).then(function (data) {
            var template = document.createElement('template');
            template.innerHTML = data.html || '';
            var rows = Array.prototype.slice.call(template.content.querySelectorAll('tr'));
            if (window.PharxmacoTables) window.PharxmacoTables.replaceRows(table, rows);
            var text = data.count === 1 ? '1 product' : Number(data.count).toLocaleString('en-US') + ' products';
            if (data.capped) text += ' (list is capped)';
            if (data.notices && data.notices.length) text += '. ' + data.notices.join(' ');
            status.textContent = text;
            loaded = true;
        }).catch(function () {
            status.textContent = 'The product forecast could not be loaded. Close this window and try again.';
        }).then(function () {
            loading = false;
        });
    }

    function open() {
        window.PharxmacoModal.open(overlay, {trigger: trigger, initialFocus: closeBtn});
        load();
    }
    function close() {
        window.PharxmacoModal.close(overlay);
    }

    trigger.addEventListener('click', open);
    closeBtn.addEventListener('click', close);
    doneBtn.addEventListener('click', close);
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) close();
    });
})();

(function () {
    function connectRange(fromId, toId, maxMonths) {
        var from = document.getElementById(fromId);
        var to = document.getElementById(toId);
        if (!from || !to) return;

        function dateMinusMonths(value, months) {
            var parts = value.split('-').map(Number);
            if (parts.length !== 3) return '';
            var d = new Date(parts[0], parts[1] - 1, parts[2]);
            d.setMonth(d.getMonth() - months);
            var y = d.getFullYear();
            var m = String(d.getMonth() + 1).padStart(2, '0');
            var day = String(d.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + day;
        }

        function sync() {
            from.max = to.value || from.getAttribute('max') || '';
            to.min = from.value || '';
            if (maxMonths && to.value) {
                from.min = dateMinusMonths(to.value, maxMonths);
            }
        }

        from.addEventListener('change', sync);
        to.addEventListener('change', sync);
        sync();
    }

    connectRange('reportDateFrom', 'reportDateTo', null);
    connectRange('forecastDateFrom', 'forecastDateTo', 24);
})();
</script>

<?= $this->endSection() ?>
