<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;

final class CashierDayReport
{
    public function __construct(private BaseConnection $db) {}

    public static function summarize(array $events, ?int $activityLimit = 100): array
    {
        $payments = array_fill_keys(['cash','gcash','card','unknown'], ['received' => 0, 'paid_out' => 0, 'net' => 0]);
        $totals = ['sales' => 0, 'transactions' => 0, 'units_sold' => 0, 'refunds' => 0, 'refund_count' => 0,
            'exchange_count' => 0, 'adjustment_count' => 0, 'cash_added' => 0, 'cash_removed' => 0,
            'discounts' => 0, 'vat_relief' => 0, 'legacy_records' => 0, 'unidentified_refund_lines' => 0];
        $products = []; $activity = [];
        foreach ($events as $event) {
            $snapshot = json_decode((string) ($event['snapshot_json'] ?? ''), true) ?: [];
            if (!empty($snapshot['baseline_only'])) continue;
            $type = $event['event_type']; $method = $event['payment_method'];
            $amount = DiscountPolicy::cents((float) $event['amount']);
            if (!isset($payments[$method])) continue;
            if (in_array($type, ['cash_in','cash_out'], true)) {
                $totals[$type === 'cash_in' ? 'cash_added' : 'cash_removed'] += abs($amount);
            } else {
                $payments[$method][$amount >= 0 ? 'received' : 'paid_out'] += abs($amount);
                $payments[$method]['net'] += $amount;
            }
            if ($type === 'sale') {
                $totals['sales'] += $amount; $totals['transactions']++;
                $totals['discounts'] += DiscountPolicy::cents((float) ($snapshot['discount'] ?? 0));
                $totals['vat_relief'] += DiscountPolicy::cents((float) ($snapshot['vat_relief'] ?? 0));
            } elseif ($type === 'refund') {
                $totals['refunds'] += abs($amount);
                if (!empty($snapshot['unidentified_refund'])) $totals['unidentified_refund_lines']++;
                else $totals['refund_count']++;
            } elseif ($type === 'exchange') $totals['exchange_count']++;
            elseif ($type === 'adjustment') $totals['adjustment_count']++;
            if (!empty($event['legacy'])) $totals['legacy_records']++;
            foreach ($snapshot['products'] ?? [] as $line) {
                $id = (int) $line['id'];
                $products[$id] ??= ['id' => $id, 'name' => $line['name'], 'sold' => 0, 'returned' => 0, 'replacements' => 0, 'quantity_adjustments' => 0, 'amount' => 0];
                $quantity = (int) $line['quantity'];
                if ($line['direction'] === 'sold') { $products[$id]['sold'] += $quantity; $totals['units_sold'] += $quantity; }
                elseif ($line['direction'] === 'returned') $products[$id]['returned'] += abs($quantity);
                elseif ($line['direction'] === 'replacement') $products[$id]['replacements'] += $quantity;
                else $products[$id]['quantity_adjustments'] += $quantity;
                $products[$id]['amount'] += DiscountPolicy::cents((float) $line['amount']);
            }
            $activity[] = ['id' => $event['id'] ?? null, 'type' => $type, 'amount' => $amount / 100.0, 'method' => $method,
                'sale_id' => $event['sale_id'] ?? null, 'invoice' => $snapshot['invoice'] ?? '', 'time' => $event['created_at'],
                'reason' => $event['reason'] ?? '', 'legacy' => !empty($event['legacy'])];
        }
        $totals['net_collections'] = array_sum(array_column($payments, 'net'));
        foreach (['sales','refunds','cash_added','cash_removed','discounts','vat_relief','net_collections'] as $key) $totals[$key] /= 100.0;
        foreach ($payments as &$payment) foreach ($payment as &$value) $value /= 100.0;
        unset($payment, $value);
        foreach ($products as &$product) $product['amount'] /= 100.0;
        unset($product);
        usort($products, static fn ($a, $b) => $b['sold'] <=> $a['sold'] ?: strcasecmp($a['name'], $b['name']));
        usort($activity, static fn ($a, $b) => strcmp($b['time'], $a['time']) ?: ((int) $b['id'] <=> (int) $a['id']));
        return $totals + ['payments' => $payments, 'products' => array_values($products),
            'activity' => $activityLimit === null ? $activity : array_slice($activity, 0, $activityLimit)];
    }

    public function forUser(int $user, int $branch, string $from, string $to): array
    {
        $zone = new \DateTimeZone('Asia/Manila');
        $first = \DateTimeImmutable::createFromFormat('!Y-m-d', $from, $zone);
        $last = \DateTimeImmutable::createFromFormat('!Y-m-d', $to, $zone);
        if (!$first || !$last || $first->format('Y-m-d') !== $from || $last->format('Y-m-d') !== $to || $last < $first || $first->diff($last)->days > 30) throw new InvalidArgumentException('Choose a valid date range of up to 31 days.');
        $start = $from . ' 00:00:00'; $end = $to . ' 23:59:59';
        $events = $this->db->table('cashier_events')->where('user_id', $user)->where('branch_id', $branch)
            ->where('created_at >=', $start)->where('created_at <=', $end)->get()->getResultArray();
        // Earlier activity can be viewed without inventing opening floats or assigning old sales to shifts.
        $sales = $this->db->table('sales s')->select('s.*')->where('s.user_id', $user)->where('s.branch_id', $branch)
            ->whereIn('s.status', ['completed','partially_refunded','refunded'])->where('s.sale_date >=', $start)->where('s.sale_date <=', $end)
            ->where("NOT EXISTS (SELECT 1 FROM cashier_events ce WHERE ce.sale_id = s.id AND ce.event_type IN ('sale','exchange'))", null, false)->get()->getResultArray();
        $saleIds = array_column($sales, 'id'); $itemsBySale = []; $baselines = [];
        if ($saleIds) {
            foreach ($this->db->table('sale_items')->whereIn('sale_id', $saleIds)->get()->getResultArray() as $item) $itemsBySale[$item['sale_id']][] = $item;
            foreach ($this->db->table('cashier_events')->whereIn('event_key', array_map(static fn ($id) => 'baseline:' . $id, $saleIds))->get()->getResultArray() as $baseline) {
                $baselines[$baseline['sale_id']] = json_decode($baseline['snapshot_json'], true);
            }
        }
        foreach ($sales as $sale) {
            $baseline = $baselines[$sale['id']] ?? null;
            if ($baseline) $sale = array_replace($sale, $baseline['original_sale']);
            $items = $itemsBySale[$sale['id']] ?? [];
            $products = $baseline['original_products'] ?? array_map(static fn ($item) => CashierShift::product($item, (int) $item['quantity'], DiscountPolicy::cents(StatutoryDiscount::netItem($item)), 'sold'), $items);
            $exchange = ExchangeRecord::forSale($sale, $this->db);
            $amount = (float) $sale['final_total'];
            if ($exchange) {
                $amount = $exchange['due'] - $exchange['payout'];
                foreach ($products as &$line) $line['direction'] = 'replacement';
                unset($line);
                $returned = $this->db->table('refund_items')->where('refund_event_id', $exchange['event_id'])->where('sale_id', $exchange['original_id'])->get()->getResultArray();
                foreach ($returned as $item) $products[] = CashierShift::product($item, (int) $item['quantity_refunded'], -DiscountPolicy::cents((float) $item['refund_subtotal']), 'returned');
            }
            $events[] = ['id' => $sale['id'], 'legacy' => true, 'event_type' => $exchange ? 'exchange' : 'sale', 'payment_method' => $sale['payment_method'],
                'amount' => $amount, 'sale_id' => $sale['id'], 'created_at' => $sale['sale_date'], 'snapshot_json' => json_encode(['invoice' => $sale['invoice_no'], 'products' => $products,
                    'discount' => (float) $sale['discount_amount'], 'vat_relief' => array_sum(array_column($items, 'vat_exemption_amount'))], JSON_THROW_ON_ERROR)];
        }
        $refunds = $this->db->table('refund_items r')->select('r.*, s.invoice_no')->join('sales s', 's.id = r.sale_id')
            ->where('r.refunded_by', $user)->where('s.branch_id', $branch)->where('r.created_at >=', $start)->where('r.created_at <=', $end)->get()->getResultArray();
        $groups = [];
        foreach ($refunds as $refund) {
            $key = !empty($refund['refund_event_id']) ? 'refund:' . $refund['refund_event_id'] : 'legacy-refund:' . $refund['id'];
            $groups[$key][] = $refund;
        }
        $recordedKeys = array_fill_keys(array_column($events, 'event_key'), true);
        foreach ($groups as $key => $group) {
            if (isset($recordedKeys[$key])) continue;
            $head = $group[0];
            if (!empty($head['refund_event_id']) && $this->db->table('sales')->where('invoice_no', 'EXC-' . $head['sale_id'] . '-' . $head['refund_event_id'])->countAllResults()) continue;
            $method = $head['refund_method'] ?? '';
            if (!in_array($method, ['cash','gcash','card'], true)) $method = 'unknown';
            $products = array_map(static fn ($r) => CashierShift::product($r, (int) $r['quantity_refunded'], -DiscountPolicy::cents((float) $r['refund_subtotal']), 'returned'), $group);
            $events[] = ['id' => $head['id'], 'legacy' => true, 'event_type' => 'refund', 'payment_method' => $method,
                'amount' => -array_sum(array_column($group, 'refund_subtotal')), 'sale_id' => $head['sale_id'], 'created_at' => $head['created_at'], 'reason' => $head['reason'],
                'snapshot_json' => json_encode(['invoice' => $head['invoice_no'], 'products' => $products, 'unidentified_refund' => empty($head['refund_event_id'])], JSON_THROW_ON_ERROR)];
        }
        $summary = self::summarize($events);
        $summary['unknown_refund_amount'] = array_sum(array_map(static fn ($r) => in_array($r['refund_method'] ?? '', ['cash','gcash','card'], true) ? 0 : (float) $r['refund_subtotal'], $refunds));
        return $summary + ['from' => $from, 'to' => $to, 'generated_at' => CashierShift::now()];
    }
}
