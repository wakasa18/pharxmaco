<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use DateTimeImmutable;

class Dashboard extends BaseController
{
    private const REVENUE_STATUSES = ['completed', 'partially_refunded'];

    /** Seconds to cache the heavy, slow-moving aggregates (12-month trend, top products). */
    private const CACHE_TTL = 180;

    protected $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    // ---------------------------------------------------------------------
    // Query helpers
    // ---------------------------------------------------------------------

    private function refundedAmountJoinSql(): string
    {
        return '(SELECT sale_id, SUM(refund_subtotal) AS refunded_amount '
            . 'FROM refund_items GROUP BY sale_id) ri';
    }

    private function refundedQuantityJoinSql(): string
    {
        return '(SELECT sale_item_id, SUM(quantity_refunded) AS qty_refunded, '
            . 'SUM(refund_subtotal) AS refunded_subtotal '
            . 'FROM refund_items GROUP BY sale_item_id) rq';
    }

    /** Net sales (after refunds) and transaction count for a date-time window. */
    private function salesSummary(string $from, string $to, int $branchId = 0): array
    {
        $builder = $this->db->table('sales s')
            ->select(
                'COUNT(s.id) AS total_transactions, '
                . 'COALESCE(SUM(s.final_total - COALESCE(ri.refunded_amount, 0)), 0) AS net_sales',
                false
            )
            ->join($this->refundedAmountJoinSql(), 'ri.sale_id = s.id', 'left', false)
            ->whereIn('s.status', self::REVENUE_STATUSES)
            ->where('s.sale_date >=', $from)
            ->where('s.sale_date <=', $to);

        if ($branchId > 0) {
            $builder->where('s.branch_id', $branchId);
        }

        return $builder->get()->getRowArray() ?? [];
    }

    /** Batches that still have stock, in active branch inventory. */
    private function activeBatchBuilder(int $branchId = 0)
    {
        $builder = $this->db->table('product_batches pb')
            ->join('branch_products bp', 'bp.id = pb.branch_product_id')
            ->join('products p', 'p.id = bp.product_id')
            ->join('branches b', 'b.id = bp.branch_id')
            ->where('pb.quantity_remaining >', 0)
            ->where('bp.status', 'active')
            ->where('bp.deleted_at', null)->where('bp.is_deleted', 0)->where('bp.is_permanently_deleted', 0)
            ->where('p.status', 'active')
            ->where('p.deleted_at IS NULL', null, false)->where('p.is_deleted', 0)->where('p.is_permanently_deleted', 0)
            ->where('b.status', 'active');

        if ($branchId > 0) {
            $builder->where('bp.branch_id', $branchId);
        }

        return $builder;
    }

    private function activeInventoryBuilder(int $branchId = 0)
    {
        $builder = $this->db->table('branch_products bp')
            ->join('products p', 'p.id = bp.product_id')
            ->join('branches b', 'b.id = bp.branch_id')
            ->where('bp.status', 'active')
            ->where('bp.deleted_at', null)->where('bp.is_deleted', 0)->where('bp.is_permanently_deleted', 0)
            ->where('p.status', 'active')
            ->where('p.deleted_at IS NULL', null, false)->where('p.is_deleted', 0)->where('p.is_permanently_deleted', 0)
            ->where('b.status', 'active');

        if ($branchId > 0) {
            $builder->where('bp.branch_id', $branchId);
        }

        return $builder;
    }

    /**
     * Small cache wrapper. If the cache backend is unavailable the dashboard
     * still works, it just runs the query every time.
     */
    private function remember(string $key, bool $fresh, callable $callback): mixed
    {
        try {
            $cache = \Config\Services::cache();
            if (!$fresh) {
                $hit = $cache->get($key);
                if ($hit !== null) {
                    return $hit;
                }
            }
        } catch (\Throwable $e) {
            return $callback();
        }

        $value = $callback();

        try {
            $cache->save($key, $value, self::CACHE_TTL);
        } catch (\Throwable $e) {
            // Ignore cache write failures.
        }

        return $value;
    }

    // ---------------------------------------------------------------------
    // Action
    // ---------------------------------------------------------------------

    public function index()
    {
        $fresh = $this->request->getGet('refresh') !== null;

        $now = new DateTimeImmutable('now');
        $today = $now->format('Y-m-d');
        $todayStart = $today . ' 00:00:00';
        $todayEnd = $today . ' 23:59:59';
        $nowSql = $now->format('Y-m-d H:i:s');
        $yesterdayStart = $now->modify('-1 day')->format('Y-m-d') . ' 00:00:00';
        $yesterdaySameTime = $now->modify('-1 day')->format('Y-m-d H:i:s');
        $nearExpiryDate = $now->modify('+30 days')->format('Y-m-d');
        $last30Start = $now->modify('-29 days')->format('Y-m-d');
        $last90Start = $now->modify('-89 days')->format('Y-m-d');

        // ---- Branch filter (validated against active branches) -------------
        $branchOptions = $this->db->table('branches')
            ->select('id, branch_name')
            ->where('status', 'active')
            ->orderBy('branch_name', 'ASC')
            ->get()->getResultArray();

        $branchId = (int) $this->request->getGet('branch');
        if (!in_array($branchId, array_map('intval', array_column($branchOptions, 'id')), true)) {
            $branchId = 0;
        }

        // ---- Account totals ---------------------------------------------------
        $totalBranches = count($branchOptions);

        $totalUsers = $this->db->table('users')
            ->where('status', 'active')
            ->countAllResults();

        // ---- Inventory: ONE query for all counts (was six) --------------------
        $t = $this->db->escape($today);
        $n = $this->db->escape($nearExpiryDate);
        $inv = $this->activeInventoryBuilder($branchId)
            ->select(
                'COUNT(*) AS records, '
                . 'COALESCE(SUM(bp.stock), 0) AS total_stock, '
                . 'COALESCE(SUM(bp.stock <= bp.reorder_level), 0) AS low_stock, '
                . 'COALESCE(SUM(bp.stock <= 0), 0) AS out_of_stock',
                false
            )
            ->get()->getRowArray() ?? [];

        $totalBranchInventory = (int) ($inv['records'] ?? 0);
        $totalStock = (int) ($inv['total_stock'] ?? 0);
        $lowStockCount = (int) ($inv['low_stock'] ?? 0);
        $outOfStockCount = (int) ($inv['out_of_stock'] ?? 0);
        // Expiry is counted per batch with stock, matching the expiry report.
        $nearExpiryCount = $this->activeBatchBuilder($branchId)
            ->where('pb.expiration_date >', $today)->where('pb.expiration_date <=', $nearExpiryDate)->countAllResults();
        $expiredCount = $this->activeBatchBuilder($branchId)
            ->where('pb.expiration_date IS NOT NULL', null, false)->where('pb.expiration_date <=', $today)->countAllResults();

        // Product count: global when "All branches", otherwise this branch's records.
        $totalProducts = $branchId > 0
            ? $totalBranchInventory
            : $this->db->table('products')
                ->where('status', 'active')
                ->where('deleted_at IS NULL', null, false)->where('is_deleted', 0)->where('is_permanently_deleted', 0)
                ->countAllResults();

        // ---- Today vs the same moment yesterday ------------------------------
        // Comparing a half-finished today with a full yesterday always looks bad,
        // so yesterday is cut off at the current time of day.
        $todaySummary = $this->salesSummary($todayStart, $todayEnd, $branchId);
        $yesterdaySummary = $this->salesSummary($yesterdayStart, $yesterdaySameTime, $branchId);

        $todayTransactions = (int) ($todaySummary['total_transactions'] ?? 0);
        $todayRevenue = (float) ($todaySummary['net_sales'] ?? 0);
        $yesterdayRevenue = (float) ($yesterdaySummary['net_sales'] ?? 0);
        $yesterdayTransactions = (int) ($yesterdaySummary['total_transactions'] ?? 0);
        $avgSale = $todayTransactions > 0 ? $todayRevenue / $todayTransactions : 0.0;

        // ---- Alert lists (top 10 each) ----------------------------------------
        $lowStockItems = $this->activeInventoryBuilder($branchId)
            ->select('bp.id, bp.branch_id, bp.product_id, bp.stock, bp.reorder_level, '
                . 'p.product_name, p.sku, b.branch_name')
            ->where('bp.stock <= bp.reorder_level', null, false)
            ->orderBy('bp.stock', 'ASC')
            ->orderBy('p.product_name', 'ASC')
            ->limit(10)->get()->getResultArray();

        $nearExpiryItems = $this->activeBatchBuilder($branchId)
            ->select('pb.quantity_remaining AS stock, pb.batch_no, bp.reorder_level, pb.expiration_date, p.product_name, p.sku, b.branch_name')
            ->where('pb.expiration_date >', $today)
            ->where('pb.expiration_date <=', $nearExpiryDate)
            ->orderBy('pb.expiration_date', 'ASC')
            ->limit(10)->get()->getResultArray();

        $expiredItems = $this->activeBatchBuilder($branchId)
            ->select('pb.quantity_remaining AS stock, pb.batch_no, bp.reorder_level, pb.expiration_date, p.product_name, p.sku, b.branch_name')
            ->where('pb.expiration_date IS NOT NULL', null, false)
            ->where('pb.expiration_date <=', $today)
            ->orderBy('pb.expiration_date', 'ASC')
            ->limit(10)->get()->getResultArray();

        // ---- Branch comparison: always all branches, last 30 days -------------
        $statusList = "'" . implode("','", self::REVENUE_STATUSES) . "'";
        $salesByBranch = $this->db->query(
            "SELECT
                b.id AS branch_id,
                b.branch_name,
                COUNT(s.id) AS total_transactions,
                COALESCE(SUM(s.final_total - COALESCE(ri.refunded_amount, 0)), 0) AS total_amount
            FROM branches b
            LEFT JOIN sales s
                ON s.branch_id = b.id
                AND s.status IN ({$statusList})
                AND s.sale_date >= ?
            LEFT JOIN {$this->refundedAmountJoinSql()} ON ri.sale_id = s.id
            WHERE b.status = 'active'
            GROUP BY b.id, b.branch_name
            ORDER BY total_amount DESC, b.branch_name ASC",
            [$last30Start . ' 00:00:00']
        )->getResultArray();

        $inventoryByBranch = $this->db->query(
            "SELECT
                b.id AS branch_id,
                b.branch_name,
                COUNT(p.id) AS total_products,
                COALESCE(SUM(CASE WHEN p.id IS NOT NULL THEN bp.stock ELSE 0 END), 0) AS total_stock
            FROM branches b
            LEFT JOIN branch_products bp
                ON bp.branch_id = b.id
                AND bp.status = 'active'
                AND bp.deleted_at IS NULL
                AND bp.is_deleted = 0
                AND bp.is_permanently_deleted = 0
            LEFT JOIN products p
                ON p.id = bp.product_id
                AND p.status = 'active'
                AND p.deleted_at IS NULL
                AND p.is_deleted = 0
                AND p.is_permanently_deleted = 0
            WHERE b.status = 'active'
            GROUP BY b.id, b.branch_name
            ORDER BY b.branch_name ASC"
        )->getResultArray();

        // ---- Recent sales / activity ------------------------------------------
        $recentBuilder = $this->db->table('sales s')
            ->select('s.id, s.invoice_no, s.status, s.payment_method, s.sale_date, '
                . 'u.full_name, b.branch_name, '
                . '(s.final_total - COALESCE(ri.refunded_amount, 0)) AS remaining_total', false)
            ->join('users u', 'u.id = s.user_id', 'left')
            ->join('branches b', 'b.id = s.branch_id', 'left')
            ->join($this->refundedAmountJoinSql(), 'ri.sale_id = s.id', 'left', false)
            ->whereIn('s.status', self::REVENUE_STATUSES);
        if ($branchId > 0) {
            $recentBuilder->where('s.branch_id', $branchId);
        }
        $recentSales = $recentBuilder
            ->orderBy('s.sale_date', 'DESC')
            ->orderBy('s.id', 'DESC')
            ->limit(10)->get()->getResultArray();

        $recentLogs = $this->db->table('activity_logs')
            ->select('activity_logs.*, users.full_name, users.username')
            ->join('users', 'users.id = activity_logs.user_id', 'left')
            ->orderBy('activity_logs.id', 'DESC')
            ->limit(10)->get()->getResultArray();

        // ---- Daily trend (last 30 days, live) ---------------------------------
        $dailyBuilder = $this->db->table('sales s')
            ->select('DATE(s.sale_date) AS sale_day, '
                . 'SUM(s.final_total - COALESCE(ri.refunded_amount, 0)) AS revenue, '
                . 'COUNT(s.id) AS txns', false)
            ->join($this->refundedAmountJoinSql(), 'ri.sale_id = s.id', 'left', false)
            ->whereIn('s.status', self::REVENUE_STATUSES)
            ->where('s.sale_date >=', $last30Start . ' 00:00:00');
        if ($branchId > 0) {
            $dailyBuilder->where('s.branch_id', $branchId);
        }
        $dailySalesRaw = $dailyBuilder
            ->groupBy('DATE(s.sale_date)')
            ->orderBy('sale_day', 'ASC')
            ->get()->getResultArray();

        $dailyMap = [];
        foreach ($dailySalesRaw as $row) {
            $dailyMap[$row['sale_day']] = $row;
        }

        $dailyLabels = $dailyFullLabels = $dailyRevenue = $dailyTxns = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = $now->modify("-{$i} days");
            $key = $day->format('Y-m-d');
            $dailyLabels[] = $day->format('M j');
            $dailyFullLabels[] = $day->format('l, F j, Y');
            $dailyRevenue[] = isset($dailyMap[$key]) ? (float) $dailyMap[$key]['revenue'] : 0.0;
            $dailyTxns[] = isset($dailyMap[$key]) ? (int) $dailyMap[$key]['txns'] : 0;
        }

        // ---- Monthly trend + top products (cached) ----------------------------
        $monthly = $this->remember("dash_monthly_b{$branchId}", $fresh, function () use ($now, $branchId): array {
            // "first day of" avoids the 31st-of-the-month overflow bug of strtotime('-N months').
            $firstOfThisMonth = $now->modify('first day of this month')->setTime(0, 0);
            $start = $firstOfThisMonth->modify('-11 months')->format('Y-m-d 00:00:00');

            $builder = $this->db->table('sales s')
                ->select("DATE_FORMAT(s.sale_date, '%Y-%m') AS sale_month, "
                    . 'SUM(s.final_total - COALESCE(ri.refunded_amount, 0)) AS revenue, '
                    . 'COUNT(s.id) AS txns', false)
                ->join($this->refundedAmountJoinSql(), 'ri.sale_id = s.id', 'left', false)
                ->whereIn('s.status', self::REVENUE_STATUSES)
                ->where('s.sale_date >=', $start);
            if ($branchId > 0) {
                $builder->where('s.branch_id', $branchId);
            }
            $raw = $builder
                ->groupBy("DATE_FORMAT(s.sale_date, '%Y-%m')")
                ->orderBy('sale_month', 'ASC')
                ->get()->getResultArray();

            $map = [];
            foreach ($raw as $row) {
                $map[$row['sale_month']] = $row;
            }

            $labels = $revenue = $txns = [];
            for ($i = 11; $i >= 0; $i--) {
                $month = $firstOfThisMonth->modify("-{$i} months");
                $key = $month->format('Y-m');
                $labels[] = $month->format('M Y');
                $revenue[] = isset($map[$key]) ? (float) $map[$key]['revenue'] : 0.0;
                $txns[] = isset($map[$key]) ? (int) $map[$key]['txns'] : 0;
            }

            return ['labels' => $labels, 'revenue' => $revenue, 'txns' => $txns];
        });

        $topProducts = $this->remember("dash_top_b{$branchId}", $fresh, function () use ($last90Start, $branchId): array {
            $builder = $this->db->table('sale_items si')
                ->select('si.product_id, MAX(si.product_name_snapshot) AS product_name, '
                    . 'SUM(si.quantity - COALESCE(rq.qty_refunded, 0)) AS units_sold, '
                    . 'SUM((si.subtotal - COALESCE(si.discount_applied, 0) - COALESCE(si.vat_exemption_amount, 0)) '
                    . '- COALESCE(rq.refunded_subtotal, 0)) AS revenue', false)
                ->join('sales s', 's.id = si.sale_id')
                ->join($this->refundedQuantityJoinSql(), 'rq.sale_item_id = si.id', 'left', false)
                ->whereIn('s.status', self::REVENUE_STATUSES)
                ->where('s.sale_date >=', $last90Start . ' 00:00:00');
            if ($branchId > 0) {
                $builder->where('s.branch_id', $branchId);
            }

            return $builder
                ->groupBy('si.product_id')
                ->having('SUM(si.quantity - COALESCE(rq.qty_refunded, 0)) > 0', null, false)
                ->orderBy('revenue', 'DESC')
                ->orderBy('units_sold', 'DESC')
                ->limit(10)
                ->get()->getResultArray();
        });

        $branchChartData = array_map(static fn(array $row): float => (float) $row['total_amount'], $salesByBranch);
        $branchChartLabels = array_map(
            static fn(array $row): string => (string) ($row['branch_name'] ?? 'Unknown'),
            $salesByBranch
        );

        return view('dashboard/index', [
            'title' => 'Dashboard',
            'dataAsOf' => $nowSql,
            // filter
            'branchOptions' => $branchOptions,
            'selectedBranchId' => $branchId,
            // snapshot
            'totalBranches' => $totalBranches,
            'totalUsers' => $totalUsers,
            'totalProducts' => $totalProducts,
            'totalBranchInventory' => $totalBranchInventory,
            'totalStock' => $totalStock,
            // today
            'todayTransactions' => $todayTransactions,
            'todayRevenue' => $todayRevenue,
            'yesterdayRevenue' => $yesterdayRevenue,
            'yesterdayTransactions' => $yesterdayTransactions,
            'avgSale' => $avgSale,
            // alerts
            'lowStockCount' => $lowStockCount,
            'outOfStockCount' => $outOfStockCount,
            'nearExpiryCount' => $nearExpiryCount,
            'expiredCount' => $expiredCount,
            'lowStockItems' => $lowStockItems,
            'nearExpiryItems' => $nearExpiryItems,
            'expiredItems' => $expiredItems,
            'today' => $today,
            // tables
            'salesByBranch' => $salesByBranch,
            'inventoryByBranch' => $inventoryByBranch,
            'recentSales' => $recentSales,
            'recentLogs' => $recentLogs,
            // charts
            'dailyLabels' => $dailyLabels,
            'dailyFullLabels' => $dailyFullLabels,
            'dailyRevenue' => $dailyRevenue,
            'dailyTxns' => $dailyTxns,
            'monthlyLabels' => $monthly['labels'],
            'monthlyRevenue' => $monthly['revenue'],
            'monthlyTxns' => $monthly['txns'],
            'topProductLabels' => array_column($topProducts, 'product_name'),
            'topProductRevenue' => array_map(static fn(array $r): float => (float) $r['revenue'], $topProducts),
            'topProductUnits' => array_map(static fn(array $r): int => (int) $r['units_sold'], $topProducts),
            'branchChartLabels' => $branchChartLabels,
            'branchChartData' => $branchChartData,
        ]);
    }
}
