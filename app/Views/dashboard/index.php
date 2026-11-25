<?= $this->extend('layouts/staff') ?>
<?= $this->section('content') ?>

<?php
$tz = new DateTimeZone('Asia/Manila');
$now = new DateTimeImmutable('now', $tz);
$hour = (int) $now->format('G');
$greeting = $hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening');
$firstName = explode(' ', trim((string) session('full_name')))[0] ?? '';
$firstName = $firstName !== '' ? $firstName : 'there';
$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;

$selectedBranchId = (int) ($selectedBranchId ?? 0);
$selectedBranchName = 'All branches';
foreach ($branchOptions as $opt) {
    if ((int) $opt['id'] === $selectedBranchId) {
        $selectedBranchName = $opt['branch_name'];
    }
}
$showBranchCol = $selectedBranchId === 0;
$query = $selectedBranchId ? ['branch' => $selectedBranchId] : [];
$refreshUrl = site_url('dashboard') . '?' . http_build_query($query + ['refresh' => 1]);
$dataAsOf = !empty($dataAsOf) ? (new DateTimeImmutable($dataAsOf))->format('g:i A') : $now->format('g:i A');

$hasTopProducts = !empty($topProductLabels);
$hasDailySales = array_sum($dailyTxns ?? []) > 0 || array_sum($dailyRevenue ?? []) != 0;
$hasMonthlySales = array_sum($monthlyTxns ?? []) > 0 || array_sum($monthlyRevenue ?? []) != 0;

$peso = static fn($v): string => '₱' . number_format((float) $v, 2);

// Hero figure: the currency sign and the centavos sit quieter than the pesos.
$pesoHero = static function ($v): string {
    [$whole, $cents] = explode('.', number_format((float) $v, 2));
    return '<span class="dash-cur">₱</span><span class="dash-num" data-count="' . (int) str_replace(',', '', $whole) . '">' . $whole . '</span><span class="dash-dec">.' . $cents . '</span>';
};

// Percentage change; null when there is no baseline to compare with.
$pctChange = static function (float $current, float $previous): ?float {
    return $previous > 0 ? (($current - $previous) / $previous) * 100 : null;
};
$deltaChip = static function (?float $pct): string {
    if ($pct === null) {
        return '';
    }
    $dir = abs($pct) < 0.05 ? 'flat' : ($pct > 0 ? 'up' : 'down');
    $arrow = ['up' => '▲', 'down' => '▼', 'flat' => '–'][$dir];
    $word = ['up' => 'up', 'down' => 'down', 'flat' => 'unchanged'][$dir];
    return '<span class="dash-delta dash-delta-' . $dir . '"><span aria-hidden="true">' . $arrow . '</span> '
        . number_format(abs($pct), 1) . '%<span class="dash-sr"> ' . $word . '</span></span>';
};

$revenueDelta = $pctChange((float) $todayRevenue, (float) $yesterdayRevenue);
$txnDelta = $pctChange((float) $todayTransactions, (float) $yesterdayTransactions);

// 7-day sparkline for the hero figure (inline SVG, no library needed).
$spark = array_slice(array_map('floatval', $dailyRevenue ?? []), -7);
$sparkLine = [];
$sparkArea = '';
$sparkLast = [0, 22];
if (count($spark) > 1) {
    $lo = min($spark);
    $hi = max($spark);
    foreach ($spark as $i => $v) {
        $x = round($i * (156 / (count($spark) - 1)) + 2, 1);
        $y = $hi > $lo ? round(40 - (($v - $lo) / ($hi - $lo)) * 34, 1) : 22;
        $sparkLine[] = $x . ',' . $y;
        $sparkLast = [$x, $y];
    }
    $sparkArea = implode(' ', $sparkLine) . ' 158,44 2,44';
}

$attentionTabs = [
    'expired' => [
        'label' => 'Expired', 'count' => (int) $expiredCount, 'items' => $expiredItems,
        'href' => site_url('admin/expiry-report?status=expired'),
        'note' => 'In-stock items already past their expiration date. Remove them from sale.',
        'empty' => 'No expired stock requires action.',
    ],
    'low' => [
        'label' => 'Low stock', 'count' => (int) $lowStockCount, 'items' => $lowStockItems,
        'href' => site_url('products'),
        'note' => number_format((int) $outOfStockCount) . ' of these are completely out of stock. Lowest stock first.',
        'empty' => 'All active inventory records are above their reorder level.',
    ],
    'near' => [
        'label' => 'Near expiry', 'count' => (int) $nearExpiryCount, 'items' => $nearExpiryItems,
        'href' => site_url('admin/expiry-report?status=near'),
        'note' => 'In-stock items expiring within 30 days, soonest first.',
        'empty' => 'No active stock is nearing expiry.',
    ],
];
$defaultTab = $expiredCount > 0 ? 'expired' : ($lowStockCount > 0 ? 'low' : ($nearExpiryCount > 0 ? 'near' : 'expired'));
$allClear = $expiredCount + $lowStockCount + $nearExpiryCount === 0;

// Branch comparison: sales (30 days) and inventory merged into one row per branch.
$branchRows = [];
foreach ($salesByBranch as $row) {
    $branchRows[(int) $row['branch_id']] = [
        'id' => (int) $row['branch_id'], 'name' => $row['branch_name'],
        'txns' => (int) $row['total_transactions'], 'amount' => (float) $row['total_amount'],
        'products' => 0, 'stock' => 0,
    ];
}
foreach ($inventoryByBranch as $row) {
    $id = (int) $row['branch_id'];
    $branchRows[$id] = ($branchRows[$id] ?? [
        'id' => $id, 'name' => $row['branch_name'], 'txns' => 0, 'amount' => 0.0,
    ]) + ['products' => 0, 'stock' => 0];
    $branchRows[$id]['products'] = (int) $row['total_products'];
    $branchRows[$id]['stock'] = (int) $row['total_stock'];
}
$branchTotal = array_sum(array_column($branchRows, 'amount'));

// Top products: ranked list, bar widths are relative to the leader of each measure.
$topMaxRevenue = $hasTopProducts ? max(array_map('floatval', $topProductRevenue)) : 0;
$topMaxUnits = $hasTopProducts ? max(array_map('floatval', $topProductUnits)) : 0;

$cssFile = FCPATH . 'assets/css/dashboard.css';
$cssVersion = is_file($cssFile) ? filemtime($cssFile) : time();
?>

<link rel="stylesheet" href="<?= base_url('assets/css/dashboard.css') ?>?v=<?= (int) $cssVersion ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/dashboard-premium.css') ?>?v=<?= (int) (is_file(FCPATH . 'assets/css/dashboard-premium.css') ? filemtime(FCPATH . 'assets/css/dashboard-premium.css') : time()) ?>">

<div class="dash-page">
    <header class="dash-head">
        <div class="dash-head-main">
            <h1 id="dashboardGreeting" data-name="<?= esc($firstName, 'attr') ?>">Good <?= esc($greeting) ?>, <?= esc($firstName) ?></h1>
            <time
                class="dash-clock"
                id="dashboardPhilippineTime"
                datetime="<?= esc($now->format(DateTimeInterface::ATOM), 'attr') ?>"
                data-server-epoch-ms="<?= esc((string) ((int) $now->format('U') * 1000), 'attr') ?>"
                title="Philippine Standard Time (UTC+8)"
            >
                <span class="dash-clock-date"><?= esc($now->format('l, F j')) ?></span>
                <span class="dash-clock-time"><?= esc($now->format('g:i A')) ?></span>
            </time>
        </div>
        <div class="dash-head-tools">
            <form class="dash-filter" id="dashFilter" method="get" action="<?= site_url('dashboard') ?>">
                <label class="dash-sr" for="dashBranch">Branch</label>
                <select class="dash-select" id="dashBranch" name="branch">
                    <option value="0">All branches</option>
                    <?php foreach ($branchOptions as $opt): ?>
                        <option value="<?= (int) $opt['id'] ?>" <?= (int) $opt['id'] === $selectedBranchId ? 'selected' : '' ?>><?= esc($opt['branch_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <noscript><button type="submit" class="dash-btn">Apply</button></noscript>
            </form>
            <a class="dash-refresh" href="<?= esc($refreshUrl, 'attr') ?>" title="Reload and bypass cached trend data">Updated <?= esc($dataAsOf) ?>. Refresh</a>
        </div>
    </header>

    <nav class="dash-toolbar" aria-label="Quick actions">
        <a class="dash-btn dash-btn-primary" href="<?= site_url('cashier/sales') ?>"><?= view('partials/icon', ['name' => 'cart']) ?><span>Open POS</span></a>
        <a class="dash-btn" href="<?= site_url('products/create') ?>"><?= view('partials/icon', ['name' => 'plus']) ?><span>Add product</span></a>
        <a class="dash-btn" href="<?= site_url('admin/reports') ?>"><?= view('partials/icon', ['name' => 'chart']) ?><span>Reports</span></a>
        <a class="dash-btn" href="<?= site_url('admin/expiry-report') ?>"><?= view('partials/icon', ['name' => 'clock']) ?><span>Expiry report</span></a>
    </nav>

    <?php if ($expiredCount > 0 || $outOfStockCount > 0): ?>
        <div class="dash-notice" role="status">
            <span>
                <?php if ($expiredCount > 0): ?><strong><?= number_format((int) $expiredCount) ?> expired</strong> record<?= $expiredCount === 1 ? '' : 's' ?> still in stock<?php endif; ?>
                <?= ($expiredCount > 0 && $outOfStockCount > 0) ? ' and ' : '' ?>
                <?php if ($outOfStockCount > 0): ?><strong><?= number_format((int) $outOfStockCount) ?> out of stock</strong><?php endif; ?>
                <?= $selectedBranchId ? ' in ' . esc($selectedBranchName) : '' ?>
            </span>
            <a href="#dashAttention">Review</a>
        </div>
    <?php endif; ?>

    <section class="dash-today" id="dashOverview" aria-label="Today, <?= esc($selectedBranchName, 'attr') ?>">
        <a class="dash-stat dash-stat-hero" href="<?= site_url('admin/reports') ?>">
            <span class="dash-label" title="Net sales after refunds">Net sales today</span>
            <strong class="dash-figure"><?= $pesoHero($todayRevenue) ?></strong>
            <span class="dash-compare">
                <?= $deltaChip($revenueDelta) ?>
                <?php if ($yesterdayRevenue > 0): ?>
                    <span>vs <?= $peso($yesterdayRevenue) ?> at this time yesterday</span>
                <?php else: ?>
                    <span>No sales at this time yesterday to compare</span>
                <?php endif; ?>
            </span>
            <?php if ($sparkLine): ?>
                <figure class="dash-spark-fig">
                    <div class="dash-spark-box">
                        <svg class="dash-spark" viewBox="2 0 160 44" preserveAspectRatio="none" role="img" aria-label="Net sales trend for the last 7 days">
                            <polygon points="<?= esc($sparkArea, 'attr') ?>" fill="currentColor"/>
                            <polyline points="<?= esc(implode(' ', $sparkLine), 'attr') ?>" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span class="dash-spark-dot" style="left:<?= round(($sparkLast[0] - 2) / 160 * 100, 2) ?>%;top:<?= round($sparkLast[1] / 44 * 100, 2) ?>%" aria-hidden="true"></span>
                    </div>
                    <figcaption>Last 7 days</figcaption>
                </figure>
            <?php endif; ?>
        </a>
        <a class="dash-stat" href="<?= site_url('cashier/sales/history') ?>">
            <span class="dash-label">Transactions</span>
            <strong class="dash-figure" data-count="<?= (int) $todayTransactions ?>"><?= number_format((int) $todayTransactions) ?></strong>
            <span class="dash-compare">
                <?= $deltaChip($txnDelta) ?>
                <span><?= number_format((int) $yesterdayTransactions) ?> at this time yesterday</span>
            </span>
        </a>
        <a class="dash-stat" href="<?= site_url('admin/reports') ?>">
            <span class="dash-label">Average sale</span>
            <strong class="dash-figure"><?= $pesoHero($avgSale) ?></strong>
            <span class="dash-compare"><span>Net sales divided by transactions</span></span>
        </a>
    </section>

    <section class="dash-panel dash-attention" id="dashAttention" data-default="<?= esc($defaultTab, 'attr') ?>">
        <div class="dash-panel-head">
            <div>
                <h2>Needs attention</h2>
                <p>Stock in <?= esc($selectedBranchName) ?> that needs action. A product can appear in more than one list.</p>
            </div>
        </div>
        <?php if ($allClear): ?>
            <div class="dash-allclear">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>
                <div><strong>All clear</strong><span>No expired, low-stock or near-expiry items right now.</span></div>
            </div>
        <?php else: ?>
            <div class="dash-tabs" role="tablist" aria-label="Alert categories">
                <?php foreach ($attentionTabs as $key => $tab): ?>
                    <button type="button" role="tab" class="dash-tab dash-tab-<?= $key ?>" id="tab-<?= $key ?>" aria-controls="panel-<?= $key ?>" aria-selected="false" data-tab="<?= $key ?>">
                        <span><?= esc($tab['label']) ?></span>
                        <span class="dash-count<?= $tab['count'] > 0 ? ' has' : '' ?>"><?= number_format($tab['count']) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <?php foreach ($attentionTabs as $key => $tab): ?>
                <div class="dash-tabpanel" role="tabpanel" id="panel-<?= $key ?>" aria-labelledby="tab-<?= $key ?>" data-panel="<?= $key ?>">
                    <p class="dash-panel-note"><?= esc($tab['note']) ?></p>
                    <?php if (!empty($tab['items'])): ?>
                        <div class="table-wrap">
                            <table class="dash-table mobile-card-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <?php if ($showBranchCol): ?><th>Branch</th><?php endif; ?>
                                        <?php if ($key === 'low'): ?>
                                            <th class="num">Stock</th><th class="num">Reorder level</th>
                                        <?php else: ?>
                                            <th>Expiry</th><th><?= $key === 'expired' ? 'Expired' : 'Days left' ?></th><th class="num">Stock</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($tab['items'] as $item): ?>
                                    <tr>
                                        <td data-label="Product"><div><strong><?= esc($item['product_name']) ?></strong><div class="sub"><?= esc($item['sku'] ?: 'No SKU') ?></div></div></td>
                                        <?php if ($showBranchCol): ?><td data-label="Branch"><?= esc($item['branch_name'] ?: '—') ?></td><?php endif; ?>
                                        <?php if ($key === 'low'): ?>
                                            <td data-label="Stock" class="num">
                                                <?php if ((int) $item['stock'] <= 0): ?>
                                                    <span class="badge badge-danger">Out of stock</span>
                                                <?php else: ?>
                                                    <span class="text-danger-strong"><?= number_format((int) $item['stock']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Reorder level" class="num"><?= number_format((int) $item['reorder_level']) ?></td>
                                        <?php else: ?>
                                            <?php
                                            $days = (int) (new DateTimeImmutable($today))->diff(new DateTimeImmutable($item['expiration_date']))->format('%r%a');
                                            $abs = abs($days);
                                            ?>
                                            <td data-label="Expiry"><?= esc(\App\Libraries\DisplayDate::date($item['expiration_date'])) ?><?php if (!empty($item['batch_no'])): ?><br><small class="muted">Lot <?= esc($item['batch_no']) ?></small><?php endif; ?></td>
                                            <td data-label="<?= $key === 'expired' ? 'Expired' : 'Days left' ?>">
                                                <span class="<?= $key === 'expired' ? 'text-danger-strong' : 'text-warning-strong' ?>">
                                                    <?= $key === 'expired'
                                                        ? ($days === 0 ? 'Today' : number_format($abs) . ' day' . ($abs === 1 ? '' : 's') . ' ago')
                                                        : ($days === 0 ? 'Today' : number_format($abs) . ' day' . ($abs === 1 ? '' : 's')) ?>
                                                </span>
                                            </td>
                                            <td data-label="Stock" class="num"><?= number_format((int) $item['stock']) ?></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="dash-panel-foot">
                            <span>Showing <?= number_format(count($tab['items'])) ?> of <?= number_format($tab['count']) ?></span>
                            <a class="dash-link" href="<?= esc($tab['href'], 'attr') ?>">View all</a>
                        </div>
                    <?php else: ?>
                        <div class="dash-empty"><?= esc($tab['empty']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <div class="dash-split" id="dashTrends">
        <section class="dash-panel">
            <div class="dash-panel-head">
                <div>
                    <h2>Sales trend</h2>
                    <p>Net sales after refunds, with transactions for the same period.</p>
                </div>
                <div class="dash-seg" role="group" aria-label="Sales trend period">
                    <button type="button" class="dash-seg-btn active" id="tab30d" aria-pressed="true" data-trend="daily">30 days</button>
                    <button type="button" class="dash-seg-btn" id="tab12m" aria-pressed="false" data-trend="monthly">12 months</button>
                </div>
            </div>
            <div class="dash-trend-summary" aria-live="polite">
                <div><span id="dashTrendPeriod">Net sales, last 30 days</span><strong id="dashTrendRevenue"><?= $peso(array_sum($dailyRevenue)) ?></strong></div>
                <div><span>Transactions</span><strong id="dashTrendTransactions"><?= number_format(array_sum($dailyTxns)) ?></strong></div>
            </div>
            <div class="dash-chart">
                <div class="dash-chart-panel active" id="panel30d"><div class="dash-chart-wrap"><canvas id="chartDaily" role="img" aria-label="Sales and transaction trend over the last 30 days" <?= !$hasDailySales ? 'hidden' : '' ?>></canvas><?php if (!$hasDailySales): ?><div class="dash-chart-empty">No sales recorded in the last 30 days. Completed sales will appear here.</div><?php endif; ?></div></div>
                <div class="dash-chart-panel" id="panel12m"><div class="dash-chart-wrap"><canvas id="chartMonthly" role="img" aria-label="Sales and transaction trend over the last 12 months" <?= !$hasMonthlySales ? 'hidden' : '' ?>></canvas><?php if (!$hasMonthlySales): ?><div class="dash-chart-empty">No sales recorded in the last 12 months. Completed sales will appear here.</div><?php endif; ?></div></div>
                <div class="dash-chart-empty" id="chartLibraryError" hidden>Charts could not load. Refresh the page when the internet connection is stable.</div>
            </div>
        </section>

        <section class="dash-panel">
            <div class="dash-panel-head">
                <div>
                    <h2>Top products</h2>
                    <p>Best sellers over the last 90 days.</p>
                </div>
                <div class="dash-seg" role="group" aria-label="Top product measure">
                    <button type="button" class="dash-seg-btn active" id="tabTopRev" aria-pressed="true" data-top="revenue" <?= !$hasTopProducts ? 'disabled' : '' ?>>Revenue</button>
                    <button type="button" class="dash-seg-btn" id="tabTopUnits" aria-pressed="false" data-top="units" <?= !$hasTopProducts ? 'disabled' : '' ?>>Units</button>
                </div>
            </div>
            <?php if ($hasTopProducts): ?>
                <ol class="dash-rank" id="dashRank" data-mode="revenue">
                    <?php foreach ($topProductLabels as $i => $label): ?>
                        <?php
                        $rev = (float) ($topProductRevenue[$i] ?? 0);
                        $units = (int) round((float) ($topProductUnits[$i] ?? 0));
                        $wRev = $topMaxRevenue > 0 ? max(2, round($rev / $topMaxRevenue * 100, 1)) : 0;
                        $wUnits = $topMaxUnits > 0 ? max(2, round($units / $topMaxUnits * 100, 1)) : 0;
                        ?>
                        <li style="--i:<?= (int) $i ?>">
                            <span class="dash-rank-n"><?= $i + 1 ?></span>
                            <div class="dash-rank-body">
                                <div class="dash-rank-line">
                                    <span class="dash-rank-name" title="<?= esc($label, 'attr') ?>"><?= esc($label) ?></span>
                                    <span class="dash-rank-val">
                                        <span data-v="revenue"><?= $peso($rev) ?></span>
                                        <span data-v="units"><?= number_format($units) ?> unit<?= $units === 1 ? '' : 's' ?></span>
                                    </span>
                                </div>
                                <span class="dash-rank-bar" aria-hidden="true"><span style="--w-revenue:<?= number_format($wRev, 1, '.', '') ?>%;--w-units:<?= number_format($wUnits, 1, '.', '') ?>%"></span></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <div class="dash-empty">No product sales are available for the last 90 days.</div>
            <?php endif; ?>
        </section>
    </div>

    <section class="dash-panel" id="dashBranches">
        <div class="dash-panel-head">
            <div>
                <h2>Branches</h2>
                <p>Totals follow the branch filter. The table always lists every active branch, with sales from the last 30 days.</p>
            </div>
            <a class="dash-link" href="<?= site_url('admin/reports') ?>">Open reports</a>
        </div>
        <div class="dash-glance">
            <a class="dash-glance-item" href="<?= site_url('products') ?>">
                <span class="dash-label">Active products</span>
                <strong data-count="<?= (int) $totalProducts ?>"><?= number_format((int) $totalProducts) ?></strong>
                <span class="dash-sub"><?= number_format((int) $totalBranchInventory) ?> branch records</span>
            </a>
            <a class="dash-glance-item" href="<?= site_url('products') ?>">
                <span class="dash-label">Stock units</span>
                <strong data-count="<?= (int) $totalStock ?>"><?= number_format((int) $totalStock) ?></strong>
                <span class="dash-sub">In <?= esc(strtolower($selectedBranchName) === 'all branches' ? 'active branches' : $selectedBranchName) ?></span>
            </a>
            <a class="dash-glance-item" href="<?= site_url('admin/branches') ?>">
                <span class="dash-label">Active branches</span>
                <strong data-count="<?= (int) $totalBranches ?>"><?= number_format((int) $totalBranches) ?></strong>
                <span class="dash-sub">Currently operating</span>
            </a>
            <a class="dash-glance-item" href="<?= site_url('admin/users') ?>">
                <span class="dash-label">Active users</span>
                <strong data-count="<?= (int) $totalUsers ?>"><?= number_format((int) $totalUsers) ?></strong>
                <span class="dash-sub">Administrators and cashiers</span>
            </a>
        </div>
        <div class="table-wrap">
            <table class="dash-table mobile-card-table">
                <thead><tr><th>Branch</th><th class="num">Products</th><th class="num">Stock units</th><th class="num">Transactions</th><th class="num">Net sales</th><th>Share</th></tr></thead>
                <tbody>
                <?php if (!empty($branchRows)): ?>
                    <?php foreach ($branchRows as $row): ?>
                        <?php $share = $branchTotal > 0 ? ($row['amount'] / $branchTotal) * 100 : 0; ?>
                        <tr class="<?= $row['id'] === $selectedBranchId ? 'is-current' : '' ?>">
                            <td data-label="Branch"><strong><?= esc($row['name'] ?: '—') ?></strong></td>
                            <td data-label="Products" class="num"><?= number_format($row['products']) ?></td>
                            <td data-label="Stock units" class="num"><?= number_format($row['stock']) ?></td>
                            <td data-label="Transactions" class="num"><?= number_format($row['txns']) ?></td>
                            <td data-label="Net sales" class="num amount"><?= $peso($row['amount']) ?></td>
                            <td data-label="Share">
                                <div class="dash-share"><span class="dash-share-bar"><span style="width:<?= number_format($share, 1, '.', '') ?>%"></span></span><span><?= number_format($share, 0) ?>%</span></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="dash-empty">No active branches are available.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="dash-split dash-split-end" id="dashActivity">
        <section class="dash-panel">
            <div class="dash-panel-head">
                <div>
                    <h2>Recent sales</h2>
                    <p>Latest completed and partially refunded transactions<?= $selectedBranchId ? ' in ' . esc($selectedBranchName) : '' ?>.</p>
                </div>
                <a class="dash-link" href="<?= site_url('cashier/sales/history') ?>">Sales history</a>
            </div>
            <div class="table-wrap">
                <table class="dash-table mobile-card-table">
                    <thead><tr><th>Invoice</th><th>Cashier</th><th>Payment</th><th>Status</th><th class="num">Net amount</th></tr></thead>
                    <tbody>
                    <?php if (!empty($recentSales)): ?>
                        <?php foreach ($recentSales as $sale): ?>
                            <?php $isPartial = ($sale['status'] ?? '') === 'partially_refunded'; ?>
                            <tr>
                                <td data-label="Invoice">
                                    <a class="dash-invoice" href="<?= site_url('cashier/sales/view/' . (int) $sale['id']) ?>"><?= esc($sale['invoice_no']) ?></a>
                                    <div class="sub"><?= esc(\App\Libraries\DisplayDate::dateTime($sale['sale_date'])) ?></div>
                                </td>
                                <td data-label="Cashier">
                                    <?= esc($sale['full_name'] ?: '—') ?>
                                    <div class="sub"><?= esc($sale['branch_name'] ?: '—') ?></div>
                                </td>
                                <td data-label="Payment"><span class="badge badge-neutral"><?= esc(ucfirst((string) ($sale['payment_method'] ?? '—'))) ?></span></td>
                                <td data-label="Status"><span class="badge <?= $isPartial ? 'badge-warning' : 'badge-success' ?>"><?= $isPartial ? 'Partially refunded' : 'Completed' ?></span></td>
                                <td data-label="Net amount" class="num amount"><?= $peso($sale['remaining_total'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="dash-empty">No recent completed sales.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="dash-panel">
            <div class="dash-panel-head">
                <div>
                    <h2>Team activity</h2>
                    <p>Latest recorded actions.</p>
                </div>
                <a class="dash-link" href="<?= site_url('admin/activity-logs') ?>">All logs</a>
            </div>
            <?php if (!empty($recentLogs)): ?>
                <ul class="dash-feed">
                    <?php foreach ($recentLogs as $log): ?>
                        <li>
                            <div>
                                <strong><?= esc($log['full_name'] ?? 'Unknown user') ?></strong>
                                <span><?= esc($log['activity'] ?? '—') ?></span>
                            </div>
                            <time><?= !empty($log['log_time']) ? esc(\App\Libraries\DisplayDate::dateTime($log['log_time'])) : '—' ?></time>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="dash-empty">No activity has been recorded yet.</div>
            <?php endif; ?>
        </section>
    </div>
</div>

<script src="<?= base_url('assets/js/dashboard-premium.js') ?>?v=<?= (int) (is_file(FCPATH . 'assets/js/dashboard-premium.js') ? filemtime(FCPATH . 'assets/js/dashboard-premium.js') : time()) ?>" defer></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const byId = id => document.getElementById(id);

    // ---- Branch filter: submit as soon as a branch is chosen ----------------
    const filter = byId('dashFilter');
    filter?.querySelector('select')?.addEventListener('change', () => {
        filter.requestSubmit ? filter.requestSubmit() : filter.submit();
    });

    // ---- Philippine clock + greeting (writes only when the text changes) ----
    const greetingElement = byId('dashboardGreeting');
    const clock = byId('dashboardPhilippineTime');
    const clockDate = clock?.querySelector('.dash-clock-date');
    const clockTime = clock?.querySelector('.dash-clock-time');
    const serverEpochMs = Number(clock?.dataset.serverEpochMs || 0);
    const startedAt = performance.now();
    const phDate = new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Manila', weekday: 'long', month: 'long', day: 'numeric' });
    const phTime = new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Manila', hour: 'numeric', minute: '2-digit', hour12: true });
    const phHour = new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Manila', hour: 'numeric', hourCycle: 'h23' });
    const setText = (el, text) => { if (el && el.textContent !== text) el.textContent = text; };
    const tickClock = () => {
        if (!serverEpochMs) return;
        const current = new Date(serverEpochMs + (performance.now() - startedAt));
        const hour = Number(phHour.format(current));
        const part = hour < 12 ? 'morning' : (hour < 18 ? 'afternoon' : 'evening');
        setText(clockDate, phDate.format(current));
        setText(clockTime, phTime.format(current));
        if (clock) clock.dateTime = current.toISOString();
        setText(greetingElement, `Good ${part}, ${greetingElement?.dataset.name || 'there'}`);
    };
    tickClock();
    setInterval(tickClock, 5000);

    // ---- Segmented switches --------------------------------------------------
    const setPressed = (activeId, inactiveId) => {
        [[activeId, true], [inactiveId, false]].forEach(([id, on]) => {
            const el = byId(id);
            el?.classList.toggle('active', on);
            el?.setAttribute('aria-pressed', String(on));
        });
    };

    // Top products: pure CSS/DOM, no chart library needed.
    const rank = byId('dashRank');
    document.querySelectorAll('[data-top]').forEach(btn => btn.addEventListener('click', () => {
        if (!rank) return;
        const revenueMode = btn.dataset.top === 'revenue';
        rank.dataset.mode = revenueMode ? 'revenue' : 'units';
        revenueMode ? setPressed('tabTopRev', 'tabTopUnits') : setPressed('tabTopUnits', 'tabTopRev');
    }));

    // ---- Alerts tabs ----------------------------------------------------------
    const attention = byId('dashAttention');
    if (attention) {
        const tabs = [...attention.querySelectorAll('[role="tab"]')];
        const panels = [...attention.querySelectorAll('[role="tabpanel"]')];
        const select = (key, focus = false) => {
            tabs.forEach(tab => {
                const on = tab.dataset.tab === key;
                tab.setAttribute('aria-selected', String(on));
                tab.tabIndex = on ? 0 : -1;
                if (on && focus) tab.focus();
            });
            panels.forEach(panel => { panel.hidden = panel.dataset.panel !== key; });
        };
        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => select(tab.dataset.tab));
            tab.addEventListener('keydown', event => {
                const step = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
                if (!step) return;
                event.preventDefault();
                select(tabs[(index + step + tabs.length) % tabs.length].dataset.tab, true);
            });
        });
        if (tabs.length) select(attention.dataset.default);
    }

    // ---- Sales trend chart ----------------------------------------------------
    const chartError = byId('chartLibraryError');
    if (typeof window.Chart === 'undefined') {
        [byId('tab30d'), byId('tab12m')].forEach(button => { if (button) button.disabled = true; });
        document.querySelectorAll('.dash-page canvas').forEach(canvas => {
            if (canvas.hidden) return;
            canvas.hidden = true;
            const message = document.createElement('div');
            message.className = 'dash-chart-empty';
            message.textContent = 'Chart unavailable. You can still review the totals and tables.';
            canvas.parentElement.appendChild(message);
        });
        if (chartError) chartError.hidden = false;
        return;
    }

    const daily = {
        labels: <?= json_encode($dailyLabels, $jsonFlags) ?>,
        full: <?= json_encode($dailyFullLabels, $jsonFlags) ?>,
        revenue: <?= json_encode($dailyRevenue, $jsonFlags) ?>,
        txns: <?= json_encode($dailyTxns, $jsonFlags) ?>
    };
    const monthly = {
        labels: <?= json_encode($monthlyLabels, $jsonFlags) ?>,
        revenue: <?= json_encode($monthlyRevenue, $jsonFlags) ?>,
        txns: <?= json_encode($monthlyTxns, $jsonFlags) ?>
    };

    const reducedMotion = () => matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('reduce-motion');
    const themeValue = name => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    const isDark = () => document.documentElement.classList.contains('dark');
    const gridColor = () => isDark() ? 'rgba(255,255,255,.06)' : 'rgba(15,23,42,.06)';
    const tickColor = () => themeValue('--muted');
    const surfaceColor = () => themeValue('--surface');
    const alpha = (color, a) => /^#[0-9a-f]{6}$/i.test(color) ? color + Math.round(a * 255).toString(16).padStart(2, '0') : color;

    Chart.defaults.font.family = "Inter, system-ui, -apple-system, 'Segoe UI', sans-serif";
    Chart.defaults.font.size = 11;
    const charts = [];

    const money = value => '₱' + Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const compactMoney = value => {
        const n = Number(value || 0);
        return '₱' + (Math.abs(n) >= 1000 ? (n / 1000).toFixed(n % 1000 === 0 ? 0 : 1) + 'k' : n.toLocaleString('en-PH'));
    };

    // Soft fade under the sales line; re-evaluated on every draw, so it follows theme changes.
    const areaFill = context => {
        const { chart } = context;
        const area = chart.chartArea;
        const primary = themeValue('--primary');
        if (!area) return alpha(primary, .1);
        const gradient = chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
        gradient.addColorStop(0, alpha(primary, .2));
        gradient.addColorStop(1, alpha(primary, 0));
        return gradient;
    };

    const makeTrendChart = (id, series, titles) => {
        const canvas = byId(id);
        if (!canvas || canvas.hidden) return null;
        const chart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: series.labels,
                datasets: [
                    { label: 'Net sales', data: series.revenue, yAxisID: 'y', borderColor: themeValue('--primary'), backgroundColor: areaFill,
                      fill: true, tension: .4, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, pointHoverBorderWidth: 2,
                      pointHoverBackgroundColor: surfaceColor(), pointHoverBorderColor: themeValue('--primary') },
                    { label: 'Transactions', data: series.txns, yAxisID: 'y2', borderColor: alpha(tickColor(), .75), backgroundColor: 'transparent',
                      fill: false, tension: .4, borderWidth: 1.5, borderDash: [4, 4], pointRadius: 0, pointHoverRadius: 3 }
                ]
            },
            options: {
                animation: reducedMotion() ? false : { duration: 1200, easing: 'easeOutQuart', delay: 350 },
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', align: 'end', labels: { color: tickColor(), usePointStyle: true, pointStyle: 'circle', boxWidth: 7, boxHeight: 7, padding: 16 } },
                    tooltip: {
                        backgroundColor: surfaceColor(), borderColor: gridColor(), borderWidth: 1, padding: 12, cornerRadius: 12,
                        titleColor: themeValue('--text'), bodyColor: themeValue('--text-2'), usePointStyle: true, boxPadding: 4,
                        callbacks: {
                            title: items => titles ? titles[items[0].dataIndex] : items[0].label,
                            label: context => context.dataset.yAxisID === 'y2'
                                ? ' Transactions: ' + context.parsed.y
                                : ' Net sales: ' + money(context.parsed.y)
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { color: tickColor(), maxTicksLimit: window.innerWidth <= 640 ? 5 : 8, maxRotation: 0 } },
                    y: { position: 'left', beginAtZero: true, grid: { color: gridColor() }, border: { display: false }, ticks: { color: tickColor(), maxTicksLimit: 5, padding: 8, callback: compactMoney } },
                    y2: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, border: { display: false }, ticks: { color: tickColor(), maxTicksLimit: 5, padding: 8, precision: 0 } }
                }
            }
        });
        charts.push(chart);
        return chart;
    };

    const chartDaily = makeTrendChart('chartDaily', daily, daily.full);
    const chartMonthly = makeTrendChart('chartMonthly', monthly, null);

    const switchTrend = mode => {
        const dailyMode = mode === 'daily';
        const series = dailyMode ? daily : monthly;
        byId('dashTrendPeriod').textContent = dailyMode ? 'Net sales, last 30 days' : 'Net sales, last 12 months';
        byId('dashTrendRevenue').textContent = money(series.revenue.reduce((t, v) => t + Number(v), 0));
        byId('dashTrendTransactions').textContent = series.txns.reduce((t, v) => t + Number(v), 0).toLocaleString('en-PH');
        byId('panel30d')?.classList.toggle('active', dailyMode);
        byId('panel12m')?.classList.toggle('active', !dailyMode);
        dailyMode ? setPressed('tab30d', 'tab12m') : setPressed('tab12m', 'tab30d');
        requestAnimationFrame(() => (dailyMode ? chartDaily : chartMonthly)?.resize());
    };
    document.querySelectorAll('[data-trend]').forEach(btn => btn.addEventListener('click', () => switchTrend(btn.dataset.trend)));

    // ---- Re-theme the chart when the user changes theme/accent -----------------
    const reThemeCharts = () => {
        const primary = themeValue('--primary');
        charts.forEach(chart => {
            chart.options.animation = reducedMotion() ? false : { duration: 300 };
            const [sales, txns] = chart.data.datasets;
            sales.borderColor = primary;
            sales.pointHoverBorderColor = primary;
            sales.pointHoverBackgroundColor = surfaceColor();
            txns.borderColor = alpha(tickColor(), .75);
            Object.assign(chart.options.plugins.tooltip, {
                backgroundColor: surfaceColor(), borderColor: gridColor(),
                titleColor: themeValue('--text'), bodyColor: themeValue('--text-2')
            });
            chart.options.plugins.legend.labels.color = tickColor();
            Object.entries(chart.options.scales).forEach(([name, axis]) => {
                axis.ticks.color = tickColor();
                if (name === 'y') axis.grid.color = gridColor();
            });
            chart.update('none');
        });
    };
    new MutationObserver(reThemeCharts).observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-accent'] });
});
</script>

<?= $this->endSection() ?>
