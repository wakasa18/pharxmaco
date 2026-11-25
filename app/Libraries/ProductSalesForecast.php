<?php

namespace App\Libraries;

use Config\Database;

/**
 * Per-product sales forecast for the Reports page and its exports.
 *
 * Uses the same history range, interval, branch filter, revenue statuses and
 * Holt smoothing weights as the store-wide sales forecast, so the two agree.
 * Each product's net units and net sales (after returns, discounts and VAT
 * relief — the same basis as Best-Selling Products) are bucketed into evenly
 * spaced periods, with empty periods counted as zero, then projected forward.
 *
 * Read-only: nothing is written to forecasting_data.
 */
class ProductSalesForecast
{
    /** Kept identical to ReportsController::REVENUE_STATUSES. */
    private const REVENUE_STATUSES = ['completed', 'partially_refunded'];

    public const FUTURE_PERIODS = 7;
    public const DEFAULT_LIMIT = 10;
    /** Upper bound when every product is requested ($limit = null). */
    public const MAX_ALL = 2000;

    /** Relative per-period change in units below which a product is "steady". */
    private const STEADY_BAND = 0.02;

    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * @param int|null $limit Top-N products; null forecasts every product with net sales in the range.
     * @return array<int, array<string, mixed>> One row per product, best sellers first.
     */
    public function generate(
        string $dateFrom,
        string $dateTo,
        string $branchId,
        string $interval,
        float $alpha,
        float $beta,
        ?int $limit = self::DEFAULT_LIMIT
    ): array {
        if (!in_array($interval, ['daily', 'weekly', 'monthly'], true)) {
            $interval = 'daily';
        }
        // null = every product that sold in the range (bounded by MAX_ALL).
        $limit = $limit === null ? self::MAX_ALL : max(1, min($limit, 50));

        $start = $dateFrom . ' 00:00:00';
        $end = $dateTo . ' 23:59:59';
        $statusList = "'" . implode("','", self::REVENUE_STATUSES) . "'";
        $netSalesExpr = '(si.subtotal - COALESCE(si.discount_applied, 0) - COALESCE(si.vat_exemption_amount, 0))'
            . ' - COALESCE(rq.refunded_subtotal, 0)';
        $refundJoin = '(SELECT sale_item_id, SUM(quantity_refunded) AS qty_refunded, '
            . 'SUM(refund_subtotal) AS refunded_subtotal FROM refund_items GROUP BY sale_item_id) rq';

        // 1) Best sellers in the forecast history range.
        $topBuilder = $this->db->table('sale_items si')
            ->select("si.product_id, MAX(si.product_name_snapshot) AS product_name,"
                . " SUM(si.quantity - COALESCE(rq.qty_refunded, 0)) AS total_qty,"
                . " SUM({$netSalesExpr}) AS total_sales", false)
            ->join('sales s', 's.id = si.sale_id')
            ->join($refundJoin, 'rq.sale_item_id = si.id', 'left', false)
            ->where('s.sale_date >=', $start)
            ->where('s.sale_date <=', $end)
            ->whereIn('s.status', self::REVENUE_STATUSES);
        if (!empty($branchId)) {
            $topBuilder->where('s.branch_id', $branchId);
        }
        $top = $topBuilder
            ->groupBy('si.product_id')
            ->having('SUM(si.quantity - COALESCE(rq.qty_refunded, 0)) > 0', null, false)
            ->orderBy('total_qty', 'DESC')
            ->orderBy('total_sales', 'DESC')
            ->orderBy('si.product_id', 'ASC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        if ($top === []) {
            return [];
        }

        $productIds = array_map(static fn ($row) => (int) $row['product_id'], $top);
        $marks = implode(',', array_fill(0, count($productIds), '?'));
        $binds = array_merge([$start, $end], $productIds);
        $branchClause = '';
        if (!empty($branchId)) {
            $branchClause = 'AND s.branch_id = ?';
            $binds[] = $branchId;
        }

        // 2) Daily net units / sales for just those products.
        $sql = "
            SELECT
                si.product_id,
                DATE(s.sale_date) AS sale_day,
                SUM(si.quantity - COALESCE(rq.qty_refunded, 0)) AS qty,
                SUM({$netSalesExpr}) AS sales
            FROM sale_items si
            JOIN sales s ON s.id = si.sale_id
            LEFT JOIN {$refundJoin} ON rq.sale_item_id = si.id
            WHERE s.sale_date >= ? AND s.sale_date <= ?
                AND s.status IN ({$statusList})
                AND si.product_id IN ({$marks})
                {$branchClause}
            GROUP BY si.product_id, DATE(s.sale_date)
        ";
        $dayRows = $this->db->query($sql, $binds)->getResultArray();

        $sequence = $this->periodSequence($dateFrom, $dateTo, $interval);
        $qtyBuckets = [];
        $salesBuckets = [];
        foreach ($dayRows as $row) {
            $key = $this->periodKey(new \DateTimeImmutable((string) $row['sale_day']), $interval);
            $pid = (int) $row['product_id'];
            $qtyBuckets[$pid][$key] = ($qtyBuckets[$pid][$key] ?? 0.0) + (float) $row['qty'];
            $salesBuckets[$pid][$key] = ($salesBuckets[$pid][$key] ?? 0.0) + (float) $row['sales'];
        }

        // Learn only from complete periods (see ForecastPeriods); an unfinished
        // last period would read as a sudden drop in sales.
        $keys = array_keys($sequence);
        $span = $keys === [] ? null : ForecastPeriods::usableSpan($keys, $interval, $dateFrom, $dateTo);
        $first = $span['first'] ?? 0;
        $last = $span['last'] ?? max(0, count($keys) - 1);
        $skippedAtEnd = $keys === [] ? 0 : max(0, count($keys) - 1 - $last);

        $lastStart = $sequence === [] ? $dateTo : (string) end($sequence);
        $futureLabels = $this->futureLabels($lastStart, $interval, self::FUTURE_PERIODS);

        $results = [];
        foreach ($top as $product) {
            $pid = (int) $product['product_id'];
            $qtySeries = [];
            $salesSeries = [];
            foreach (array_keys($sequence) as $key) {
                $qtySeries[] = max(0.0, (float) ($qtyBuckets[$pid][$key] ?? 0.0));
                $salesSeries[] = max(0.0, (float) ($salesBuckets[$pid][$key] ?? 0.0));
            }

            $fitLength = max(0, $last - $first + 1);
            $fitQty = array_slice($qtySeries, $first, $fitLength);
            $qtyFit = $this->holtSmooth($fitQty, $alpha, $beta);
            $salesFit = $this->holtSmooth(array_slice($salesSeries, $first, $fitLength), $alpha, $beta);

            $future = [];
            $totalQty = 0.0;
            $totalSales = 0.0;
            foreach ($futureLabels as $i => $label) {
                $m = $skippedAtEnd + $i + 1;
                $qty = max(0.0, $qtyFit['level'] + $m * $qtyFit['trend']);
                $sales = max(0.0, $salesFit['level'] + $m * $salesFit['trend']);
                $totalQty += $qty;
                $totalSales += $sales;
                $future[] = [
                    'forecast_date' => $label,
                    'quantity' => (int) round($qty),
                    'sales' => round($sales, 2),
                ];
            }

            $periodCount = max(1, count($fitQty));
            $meanQty = array_sum($fitQty) / $periodCount;
            $relativeTrend = $qtyFit['trend'] / max($meanQty, 0.01);
            $direction = $relativeTrend > self::STEADY_BAND ? 'up'
                : ($relativeTrend < -self::STEADY_BAND ? 'down' : 'steady');

            $results[] = [
                'product_id' => $pid,
                'product_name' => (string) ($product['product_name'] ?? '-'),
                'history_qty' => (int) round((float) $product['total_qty']),
                'history_sales' => round((float) $product['total_sales'], 2),
                'next_qty' => $future[0]['quantity'] ?? 0,
                'next_sales' => $future[0]['sales'] ?? 0.0,
                'total_qty' => (int) round($totalQty),
                'total_sales' => round($totalSales, 2),
                'direction' => $direction,
                'future' => $future,
            ];
        }

        return $results;
    }

    /** Same algorithm and output shape as ReportsController::holtSmooth(). */
    private function holtSmooth(array $values, float $alpha, float $beta): array
    {
        $level = 0.0;
        $trend = 0.0;
        if ($values !== []) {
            $level = (float) $values[0];
            $trend = count($values) > 1 ? (float) ($values[1] - $values[0]) : 0.0;
            for ($i = 1, $n = count($values); $i < $n; $i++) {
                $previousLevel = $level;
                $level = ($alpha * $values[$i]) + ((1 - $alpha) * ($level + $trend));
                $trend = ($beta * ($level - $previousLevel)) + ((1 - $beta) * $trend);
            }
        }

        return ['level' => $level, 'trend' => $trend];
    }

    /** ISO week-year for weekly keys, matching the store-wide forecast buckets. */
    private function periodKey(\DateTimeInterface $date, string $interval): string
    {
        return match ($interval) {
            'weekly' => $date->format('o') . '-W' . $date->format('W'),
            'monthly' => $date->format('Y-m'),
            default => $date->format('Y-m-d'),
        };
    }

    /** @return array<string, string> period key => period start date */
    private function periodSequence(string $dateFrom, string $dateTo, string $interval): array
    {
        $periods = [];
        $end = new \DateTime($dateTo);

        if ($interval === 'weekly') {
            $cursor = (new \DateTime($dateFrom))->modify('monday this week');
            $step = '+1 week';
        } elseif ($interval === 'monthly') {
            $cursor = new \DateTime((new \DateTime($dateFrom))->format('Y-m-01'));
            $step = '+1 month';
        } else {
            $cursor = new \DateTime($dateFrom);
            $step = '+1 day';
        }

        while ($cursor <= $end) {
            $periods[$this->periodKey($cursor, $interval)] = $cursor->format('Y-m-d');
            $cursor->modify($step);
        }

        return $periods;
    }

    /** @return string[] Labels in the same format as the store-wide future forecast. */
    private function futureLabels(string $lastStart, string $interval, int $count): array
    {
        $labels = [];
        $base = new \DateTimeImmutable($lastStart);
        if ($interval === 'monthly') {
            $base = new \DateTimeImmutable($base->format('Y-m-01'));
        }

        for ($m = 1; $m <= $count; $m++) {
            $labels[] = match ($interval) {
                'weekly' => $base->modify("+{$m} week")->format('o-\WW'),
                'monthly' => $base->modify("+{$m} month")->format('Y-m'),
                default => $base->modify("+{$m} day")->format('Y-m-d'),
            };
        }

        return $labels;
    }
}
