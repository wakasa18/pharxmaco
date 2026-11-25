<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

/**
 * Batch (lot) level stock for a branch product.
 *
 * Every batch has its own expiry date and remaining quantity. Sales take stock
 * first-expiry-first-out from batches that have not expired. branch_products.stock
 * stays the total on hand and branch_products.expiration_date the nearest expiry
 * among batches that still have stock (see sync()), so screens that only read
 * branch_products keep working.
 *
 * Every method that writes expects the caller to be inside a transaction.
 */
final class StockBatches
{
    public const MAX_STOCK = 2147483647;
    public const MAX_MOVEMENT = 1000000;

    public const MSG_UNKNOWN_DELIVERY = 'The original stock delivery is unknown or differs from current stock. Use Quarantine, Damaged or Expired for a return; use a refund and new sale instead of changing this invoice quantity.';
    public const MSG_EXPIRED = 'Expired inventory cannot be returned to available stock. Select Expired or Quarantine.';
    public const MSG_NO_EXPIRY = 'The expiry at sale was not recorded. Choose Quarantine, Damaged or Expired; this return cannot enter available stock.';

    private ?int $lastLogId = null;

    public function __construct(private BaseConnection $db) {}

    /** Id of the most recent stock_logs row written by this instance. */
    public function lastLogId(): ?int
    {
        return $this->lastLogId;
    }

    public static function ready(BaseConnection $db): bool
    {
        return $db->tableExists('product_batches') && $db->tableExists('sale_item_batches') && $db->tableExists('refund_item_batches');
    }

    // ------------------------------------------------------------------
    // Input cleaning
    // ------------------------------------------------------------------

    public static function cleanBatchNo(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        if (mb_strlen($value) > 60 || preg_match('/[\x00-\x1F\x7F]/u', $value)) {
            throw new InvalidArgumentException('Batch / lot number must be 60 characters or fewer, without control characters.');
        }
        return $value;
    }

    public static function cleanExpiry(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value || $value < '1970-01-01') {
            throw new InvalidArgumentException('Enter a valid expiration date in YYYY-MM-DD format.');
        }
        return $value;
    }

    /**
     * Per-product batch numbers for batches that have no lot number: 1 for the product's
     * first batch, 2 for the next, and so on (oldest first, counting batches that are used up
     * so a number never changes). Returns [batch id => number].
     *
     * @param int[] $batchIds
     * @return array<int, int>
     */
    public static function numbers(BaseConnection $db, array $batchIds): array
    {
        $batchIds = array_values(array_unique(array_filter(array_map('intval', $batchIds), static fn ($id) => $id > 0)));
        if (!$batchIds) return [];
        $bpIds = array_column($db->table('product_batches')->select('branch_product_id')->distinct()->whereIn('id', $batchIds)->get()->getResultArray(), 'branch_product_id');
        if (!$bpIds) return [];
        $rows = $db->table('product_batches')->select('id, branch_product_id')->whereIn('branch_product_id', $bpIds)
            ->orderBy('branch_product_id', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray();
        $count = $numbers = [];
        foreach ($rows as $row) {
            $bp = (int) $row['branch_product_id'];
            $count[$bp] = ($count[$bp] ?? 0) + 1;
            $numbers[(int) $row['id']] = $count[$bp];
        }
        return $numbers;
    }

    /** SQL for the same per-product number inside a query on product_batches (alias = that table's alias). */
    public static function numberSql(string $alias = 'product_batches'): string
    {
        return '(SELECT COUNT(*) FROM product_batches nb WHERE nb.branch_product_id = ' . $alias . '.branch_product_id AND nb.id <= ' . $alias . '.id)';
    }

    /** What to show for a batch: its lot number, else "Batch N" (N = its number for this product). */
    public static function label(?string $lot, ?int $number): string
    {
        $lot = trim((string) $lot);
        if ($lot !== '') return $lot;
        return $number ? 'Batch ' . $number : 'No lot no.';
    }

    public static function newDeliveryId(): string
    {
        return bin2hex(random_bytes(16));
    }

    private static function validDelivery(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{32}$/D', $value) === 1;
    }

    // ------------------------------------------------------------------
    // SQL fragments for listing queries (alias = the branch_products alias)
    // ------------------------------------------------------------------

    /** Units that can be sold today: batches with stock whose expiry is unset or after $today. */
    public static function sellableQtySql(BaseConnection $db, string $bp, string $today): string
    {
        return '(SELECT COALESCE(SUM(sb.quantity_remaining), 0) FROM product_batches sb WHERE sb.branch_product_id = ' . $bp . '.id'
            . ' AND sb.quantity_remaining > 0 AND (sb.expiration_date IS NULL OR sb.expiration_date > ' . $db->escape($today) . '))';
    }

    /** Nearest expiry among sellable batches (the one a sale takes from first). */
    public static function sellableExpirySql(BaseConnection $db, string $bp, string $today): string
    {
        return '(SELECT MIN(sb.expiration_date) FROM product_batches sb WHERE sb.branch_product_id = ' . $bp . '.id'
            . ' AND sb.quantity_remaining > 0 AND sb.expiration_date > ' . $db->escape($today) . ')';
    }

    public static function batchCountSql(string $bp): string
    {
        return '(SELECT COUNT(*) FROM product_batches cb WHERE cb.branch_product_id = ' . $bp . '.id AND cb.quantity_remaining > 0)';
    }

    // ------------------------------------------------------------------
    // Reading
    // ------------------------------------------------------------------

    public function lockInventory(int $branchProductId): array
    {
        $row = $this->db->query('SELECT * FROM branch_products WHERE id = ? FOR UPDATE', [$branchProductId])->getRowArray();
        if (!$row) throw new RuntimeException('The branch inventory record was not found.');
        return $row;
    }

    /** Batches of a branch product, nearest expiry first (no expiry last). */
    public function batches(int $branchProductId, bool $withStockOnly = true, bool $lock = false): array
    {
        return $this->db->query('SELECT * FROM product_batches WHERE branch_product_id = ?'
            . ($withStockOnly ? ' AND quantity_remaining > 0' : '')
            . ' ORDER BY (expiration_date IS NULL), expiration_date ASC, id ASC' . ($lock ? ' FOR UPDATE' : ''), [$branchProductId])->getResultArray();
    }

    public function batch(int $batchId, bool $lock = false): ?array
    {
        return $this->db->query('SELECT * FROM product_batches WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''), [$batchId])->getRowArray() ?: null;
    }

    /** @return array{total:int, sellable:int, expired:int} */
    public function availability(int $branchProductId, string $today, bool $lock = false): array
    {
        $total = $sellable = 0;
        foreach ($this->batches($branchProductId, true, $lock) as $batch) {
            $qty = (int) $batch['quantity_remaining'];
            $total += $qty;
            if ($batch['expiration_date'] === null || $batch['expiration_date'] > $today) $sellable += $qty;
        }
        return ['total' => $total, 'sellable' => $sellable, 'expired' => $total - $sellable];
    }

    /** Nearest expiry among the batches a sale can take from (null when none of them has an expiry date). */
    public function nearestSellableExpiry(int $branchProductId, string $today): ?string
    {
        $row = $this->db->query('SELECT MIN(expiration_date) AS nearest FROM product_batches WHERE branch_product_id = ? AND quantity_remaining > 0 AND expiration_date > ?',
            [$branchProductId, $today])->getRowArray();
        return $row['nearest'] ?? null;
    }

    public function sellable(int $branchProductId, string $today, bool $lock = false): int
    {
        return $this->availability($branchProductId, $today, $lock)['sellable'];
    }

    /** Message for a sale that cannot be filled from unexpired batches. */
    public function shortageMessage(string $productName, array $availability, int $qty): string
    {
        if ($availability['sellable'] >= $qty) return '';
        if ($availability['total'] >= $qty && $availability['expired'] > 0) {
            return 'Only ' . $availability['sellable'] . ' unexpired unit(s) of "' . $productName . '" are available; the rest of its stock has expired and cannot be sold.';
        }
        if ($availability['sellable'] <= 0 && $availability['expired'] > 0) {
            return 'Product "' . $productName . '" is expired and cannot be sold.';
        }
        return 'Insufficient stock for ' . $productName;
    }

    // ------------------------------------------------------------------
    // Keeping branch_products in step with its batches
    // ------------------------------------------------------------------

    /**
     * Total on hand, nearest expiry and its delivery reference, copied to
     * branch_products. Returns the new total.
     */
    public function sync(int $branchProductId, ?string $now = null): int
    {
        $total = (int) ($this->db->query('SELECT COALESCE(SUM(quantity_remaining), 0) AS total FROM product_batches WHERE branch_product_id = ?', [$branchProductId])->getRowArray()['total'] ?? 0);
        if ($total > self::MAX_STOCK) throw new InvalidArgumentException('The product stock total exceeds the supported balance.');
        $nearest = $this->db->query('SELECT expiration_date, delivery_id FROM product_batches WHERE branch_product_id = ? AND quantity_remaining > 0'
            . ' ORDER BY (expiration_date IS NULL), expiration_date ASC, id ASC LIMIT 1', [$branchProductId])->getRowArray();
        $changes = ['stock' => $total, 'expiration_date' => $nearest['expiration_date'] ?? null, 'updated_at' => $now ?? date('Y-m-d H:i:s')];
        if ($nearest) $changes['stock_delivery_id'] = $nearest['delivery_id'];
        if (!$this->db->table('branch_products')->where('id', $branchProductId)->update($changes)) {
            throw new RuntimeException('Could not update the branch stock total.');
        }
        return $total;
    }

    /**
     * Safety net for stock changed outside the batch code (older code paths,
     * manual database edits): branch_products.stock is treated as the count and
     * the batches are adjusted to match. A surplus becomes a "reconcile" batch
     * carrying the recorded expiry; a shortage is taken from the nearest expiry first.
     */
    private function reconcile(array $bp, string $now): array
    {
        $sum = (int) ($this->db->query('SELECT COALESCE(SUM(quantity_remaining), 0) AS total FROM product_batches WHERE branch_product_id = ? FOR UPDATE', [(int) $bp['id']])->getRowArray()['total'] ?? 0);
        $stock = max(0, (int) $bp['stock']);
        if ($stock > $sum) {
            $delivery = $bp['stock_delivery_id'] ?? null;
            if (!self::validDelivery($delivery) || $this->deliveryInUse($delivery)) $delivery = self::newDeliveryId();
            $this->insertBatch($bp, $stock - $sum, $bp['expiration_date'] ?: null, null, $delivery, 'reconcile', null, $bp['cost_price'] ?? null, null, $now);
        } elseif ($stock < $sum) {
            $need = $sum - $stock;
            foreach ($this->batches((int) $bp['id'], true, true) as $batch) {
                if ($need <= 0) break;
                $take = min($need, (int) $batch['quantity_remaining']);
                $this->changeBatchQty((int) $batch['id'], -$take, $now);
                $need -= $take;
            }
        }
        return $this->lockInventory((int) $bp['id']);
    }

    private function deliveryInUse(string $delivery): bool
    {
        return (bool) $this->db->query('SELECT 1 FROM product_batches WHERE delivery_id = ? LIMIT 1', [$delivery])->getRowArray();
    }

    private function insertBatch(array $bp, int $qty, ?string $expiry, ?string $batchNo, string $delivery, string $source, ?int $supplierId, $cost, ?int $userId, string $now): int
    {
        $ok = $this->db->table('product_batches')->insert([
            'branch_product_id' => (int) $bp['id'], 'branch_id' => (int) $bp['branch_id'], 'product_id' => (int) $bp['product_id'],
            'batch_no' => $batchNo, 'expiration_date' => $expiry, 'quantity_received' => $qty, 'quantity_remaining' => $qty,
            'cost_price' => $cost, 'supplier_id' => $supplierId, 'delivery_id' => $delivery, 'source' => $source,
            'received_at' => $now, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
        ]);
        if (!$ok) throw new RuntimeException('Could not save the stock batch.');
        return (int) $this->db->insertID();
    }

    private function changeBatchQty(int $batchId, int $delta, string $now, bool $received = false): void
    {
        $sql = 'UPDATE product_batches SET quantity_remaining = quantity_remaining + ?'
            . ($received ? ', quantity_received = quantity_received + ?' : '') . ', updated_at = ? WHERE id = ?'
            . ($delta < 0 ? ' AND quantity_remaining >= ?' : '');
        $binds = $received ? [$delta, $delta, $now, $batchId] : [$delta, $now, $batchId];
        if ($delta < 0) $binds[] = -$delta;
        $this->db->query($sql, $binds);
        if ($this->db->affectedRows() !== 1) throw new RuntimeException('Batch stock changed while saving. No stock was changed.');
    }

    private function log(array $bp, ?int $batchId, string $action, int $qty, int $previous, int $next, string $remarks, int $userId, string $now, array $extra = []): void
    {
        $ok = $this->db->table('stock_logs')->insert([
            'product_id' => (int) $bp['product_id'], 'branch_id' => (int) $bp['branch_id'], 'user_id' => $userId,
            'action_type' => $action, 'quantity' => $qty, 'previous_stock' => $previous, 'new_stock' => $next,
            'remarks' => $remarks, 'batch_id' => $batchId, 'created_at' => $now,
        ] + $extra);
        if (!$ok) throw new RuntimeException('Could not save the stock movement.');
        $this->lastLogId = (int) $this->db->insertID();
    }

    // ------------------------------------------------------------------
    // Receiving
    // ------------------------------------------------------------------

    /**
     * Receive stock as a batch. A delivery with the same lot number and expiry as an
     * existing batch of this product adds to that batch; otherwise a new batch is created.
     * meta['into_batch_id'] names the batch the entry form was pre-filled from, so an unchanged
     * form tops up that batch even when it has no lot number.
     *
     * @param array $meta supplier_id, manufacturer, cost_price, source
     * @return array the batch row after receiving
     */
    public function receive(array $bp, int $qty, ?string $expiry, ?string $batchNo, int $userId, string $remarks, string $now, array $meta = []): array
    {
        if ($qty < 1 || $qty > self::MAX_STOCK) throw new InvalidArgumentException('Enter a valid positive quantity.');
        $expiry = self::cleanExpiry($expiry);
        $batchNo = self::cleanBatchNo($batchNo);
        $bp = $this->reconcile($this->lockInventory((int) $bp['id']), $now);
        $previous = (int) $bp['stock'];
        if ($previous > self::MAX_STOCK - $qty) throw new InvalidArgumentException('Enter a valid positive delivery quantity.');

        // The caller may name the batch the form was pre-filled from. Its lot and expiry must still
        // match exactly, so a changed lot or expiry always becomes a new batch.
        $existing = null;
        $intoBatchId = (int) ($meta['into_batch_id'] ?? 0);
        if ($intoBatchId > 0) {
            $candidate = $this->db->query(
                'SELECT * FROM product_batches WHERE id = ? AND branch_product_id = ? FOR UPDATE',
                [$intoBatchId, (int) $bp['id']]
            )->getRowArray();
            if ($candidate && ($candidate['batch_no'] ?? null) === $batchNo && ($candidate['expiration_date'] ?? null) === $expiry) {
                $existing = $candidate;
            }
        }
        if (!$existing && $batchNo !== null) {
            $existing = $this->db->query(
                'SELECT * FROM product_batches WHERE branch_product_id = ? AND batch_no = ? AND expiration_date <=> ? LIMIT 1 FOR UPDATE',
                [(int) $bp['id'], $batchNo, $expiry]
            )->getRowArray() ?: null;
        }
        if ($existing) {
            $this->changeBatchQty((int) $existing['id'], $qty, $now, true);
            $batchId = (int) $existing['id'];
        } else {
            $batchId = $this->insertBatch($bp, $qty, $expiry, $batchNo, self::newDeliveryId(), $meta['source'] ?? 'delivery',
                isset($meta['supplier_id']) ? (int) $meta['supplier_id'] : null, $meta['cost_price'] ?? ($bp['cost_price'] ?? null), $userId, $now);
        }
        $extra = array_intersect_key($meta, ['supplier_id' => 1, 'manufacturer' => 1]);
        $this->log($bp, $batchId, 'stock_in', $qty, $previous, $previous + $qty, $remarks, $userId, $now, $extra);
        $this->sync((int) $bp['id'], $now);
        return $this->batch($batchId);
    }

    // ------------------------------------------------------------------
    // Selling and removing (first expiry first)
    // ------------------------------------------------------------------

    /**
     * Take units for a sale from unexpired batches, nearest expiry first.
     *
     * @return array<int, array{batch_id:int, quantity:int, delivery_id:string, batch_no:?string, expiration_date:?string}>
     */
    public function sell(array $bp, int $qty, string $today, int $userId, string $remarks, string $now): array
    {
        if ($qty < 1 || $qty > self::MAX_STOCK) throw new InvalidArgumentException('Enter a valid quantity.');
        $bp = $this->reconcile($this->lockInventory((int) $bp['id']), $now);
        $rows = $this->db->query('SELECT * FROM product_batches WHERE branch_product_id = ? AND quantity_remaining > 0'
            . ' AND (expiration_date IS NULL OR expiration_date > ?) ORDER BY (expiration_date IS NULL), expiration_date ASC, id ASC FOR UPDATE',
            [(int) $bp['id'], $today])->getResultArray();
        if (array_sum(array_column($rows, 'quantity_remaining')) < $qty) {
            throw new InvalidArgumentException('Not enough unexpired stock for this sale.');
        }
        return $this->take($bp, $rows, $qty, 'stock_out', $remarks, $userId, $now);
    }

    /**
     * Remove units without a sale (disposal, supplier return, count correction).
     * Expired and nearest-expiry batches go first unless a batch is named.
     */
    public function remove(array $bp, int $qty, int $userId, string $remarks, string $now, ?int $batchId = null, string $action = 'stock_out'): array
    {
        if ($qty < 1 || $qty > self::MAX_STOCK) throw new InvalidArgumentException('Enter a valid quantity.');
        $bp = $this->reconcile($this->lockInventory((int) $bp['id']), $now);
        $rows = $this->batches((int) $bp['id'], true, true);
        if ($batchId !== null) {
            $rows = array_values(array_filter($rows, static fn ($b) => (int) $b['id'] === $batchId));
            if (!$rows) throw new InvalidArgumentException('The selected batch has no stock left.');
        }
        if (array_sum(array_column($rows, 'quantity_remaining')) < $qty) {
            throw new InvalidArgumentException('The removal quantity exceeds ' . ($batchId !== null ? 'the stock in this batch.' : 'current stock.'));
        }
        return $this->take($bp, $rows, $qty, $action, $remarks, $userId, $now);
    }

    private function take(array $bp, array $rows, int $qty, string $action, string $remarks, int $userId, string $now): array
    {
        $running = (int) $bp['stock'];
        $allocations = [];
        $left = $qty;
        foreach ($rows as $batch) {
            if ($left <= 0) break;
            $take = min($left, (int) $batch['quantity_remaining']);
            if ($take <= 0) continue;
            $this->changeBatchQty((int) $batch['id'], -$take, $now);
            $this->log($bp, (int) $batch['id'], $action, $take, $running, $running - $take, $remarks, $userId, $now);
            $running -= $take;
            $left -= $take;
            $allocations[] = ['batch_id' => (int) $batch['id'], 'quantity' => $take, 'delivery_id' => $batch['delivery_id'],
                'batch_no' => $batch['batch_no'], 'expiration_date' => $batch['expiration_date']];
        }
        if ($left > 0) throw new RuntimeException('Batch stock changed while saving. No stock was changed.');
        $this->sync((int) $bp['id'], $now);
        return $allocations;
    }

    /** Record which batches a sale line was taken from, and return the line's provenance columns. */
    public function recordSale(int $saleItemId, array $allocations, string $now): array
    {
        foreach ($allocations as $allocation) {
            $ok = $this->db->table('sale_item_batches')->insert([
                'sale_item_id' => $saleItemId, 'batch_id' => $allocation['batch_id'], 'quantity' => $allocation['quantity'], 'quantity_returned' => 0,
                'delivery_id' => $allocation['delivery_id'], 'batch_no' => $allocation['batch_no'], 'expiration_date' => $allocation['expiration_date'], 'created_at' => $now,
            ]);
            if (!$ok) throw new RuntimeException('Could not save the sale batch details.');
        }
        return self::lineProvenance($allocations);
    }

    /** sale_items.stock_delivery_id / expiration_date_at_sale: the nearest-expiry batch of the line. */
    public static function lineProvenance(array $allocations): array
    {
        $first = $allocations[0] ?? null;
        return ['stock_delivery_id' => $first['delivery_id'] ?? null, 'expiration_date_at_sale' => $first['expiration_date'] ?? null];
    }

    // ------------------------------------------------------------------
    // Returns
    // ------------------------------------------------------------------

    /**
     * Rows saying which batches a sale line came from. Lines sold before batch
     * tracking are linked through their recorded delivery reference.
     */
    private function allocationRows(array $item, bool $lock): array
    {
        $rows = $this->db->query('SELECT * FROM sale_item_batches WHERE sale_item_id = ? ORDER BY (expiration_date IS NULL), expiration_date ASC, id ASC'
            . ($lock ? ' FOR UPDATE' : ''), [(int) $item['id']])->getResultArray();
        if ($rows) {
            return array_map(static fn ($r) => $r + ['legacy' => false], $rows);
        }
        $delivery = $item['stock_delivery_id'] ?? null;
        if (!self::validDelivery($delivery)) return [];
        $batch = $this->db->query('SELECT * FROM product_batches WHERE delivery_id = ? AND product_id = ? LIMIT 1', [$delivery, (int) $item['product_id']])->getRowArray();
        if (!$batch) return [];
        $refunded = (int) ($this->db->query('SELECT COALESCE(SUM(quantity_refunded), 0) AS q FROM refund_items WHERE sale_item_id = ?', [(int) $item['id']])->getRowArray()['q'] ?? 0);
        $quantity = (int) $item['quantity'];
        return [[
            'id' => null, 'sale_item_id' => (int) $item['id'], 'batch_id' => (int) $batch['id'], 'quantity' => $quantity,
            'quantity_returned' => min($quantity, $refunded), 'delivery_id' => $delivery, 'batch_no' => $batch['batch_no'],
            'expiration_date' => $item['expiration_date_at_sale'] ?? null, 'legacy' => true,
        ]];
    }

    /**
     * Which batches the next $qty returned units of a sale line belong to (nearest
     * expiry first). Nothing is written.
     *
     * @return array{entries: array<int, array>, unattributed: int}
     */
    public function planReturn(array $item, int $qty, bool $lock = false): array
    {
        $entries = [];
        $left = max(0, $qty);
        foreach ($this->allocationRows($item, $lock) as $row) {
            if ($left <= 0) break;
            $available = (int) $row['quantity'] - (int) $row['quantity_returned'];
            $take = min($left, $available);
            if ($take <= 0) continue;
            $entries[] = ['sale_item_batch_id' => $row['id'], 'batch_id' => (int) $row['batch_id'], 'quantity' => $take, 'delivery_id' => $row['delivery_id'],
                'batch_no' => $row['batch_no'], 'expiration_date' => $row['expiration_date'], 'legacy' => $row['legacy'],
                'row_quantity' => (int) $row['quantity'], 'row_returned' => (int) $row['quantity_returned']];
            $left -= $take;
        }
        return ['entries' => $entries, 'unattributed' => $left];
    }

    /** Batches recorded for an existing refund line; older refunds fall back to the sale line's delivery. */
    public function refundPlan(array $refund, array $item, bool $lock = false): array
    {
        $rows = $this->db->query('SELECT rib.batch_id, rib.quantity, pb.delivery_id, pb.batch_no, pb.expiration_date FROM refund_item_batches rib'
            . ' JOIN product_batches pb ON pb.id = rib.batch_id WHERE rib.refund_item_id = ? ORDER BY rib.id' . ($lock ? ' FOR UPDATE' : ''), [(int) $refund['id']])->getResultArray();
        if ($rows) {
            return ['entries' => array_map(static fn ($r) => ['sale_item_batch_id' => null, 'batch_id' => (int) $r['batch_id'], 'quantity' => (int) $r['quantity'],
                'delivery_id' => $r['delivery_id'], 'batch_no' => $r['batch_no'], 'expiration_date' => $r['expiration_date'], 'legacy' => false], $rows), 'unattributed' => 0];
        }
        $delivery = $item['stock_delivery_id'] ?? null;
        $batch = self::validDelivery($delivery)
            ? $this->db->query('SELECT * FROM product_batches WHERE delivery_id = ? AND product_id = ? LIMIT 1', [$delivery, (int) $item['product_id']])->getRowArray()
            : null;
        if (!$batch) return ['entries' => [], 'unattributed' => (int) $refund['quantity_refunded']];
        return ['entries' => [['sale_item_batch_id' => null, 'batch_id' => (int) $batch['id'], 'quantity' => (int) $refund['quantity_refunded'], 'delivery_id' => $delivery,
            'batch_no' => $batch['batch_no'], 'expiration_date' => $item['expiration_date_at_sale'] ?? null, 'legacy' => true]], 'unattributed' => 0];
    }

    /** Why these units cannot go back to available stock, or null when they can. */
    public function restockProblem(array $plan, string $today, bool $lock = false): ?string
    {
        if ($plan['unattributed'] > 0 || !$plan['entries']) return self::MSG_UNKNOWN_DELIVERY;
        foreach ($plan['entries'] as $entry) {
            $batch = $this->batch((int) $entry['batch_id'], $lock);
            if (!$batch || !hash_equals((string) $entry['delivery_id'], (string) $batch['delivery_id'])) return self::MSG_UNKNOWN_DELIVERY;
            $expiry = $batch['expiration_date'];
            if ($expiry === null) return self::MSG_NO_EXPIRY;
            if ($expiry <= $today) return self::MSG_EXPIRED;
            // Lines sold before batch tracking only know the expiry recorded at the time of sale.
            if (!empty($entry['legacy']) && (string) $entry['expiration_date'] !== (string) $expiry) return self::MSG_UNKNOWN_DELIVERY;
        }
        return null;
    }

    /** Problem for the next returned unit of a sale line (used to warn before choosing "Resellable"). */
    public function lineRestockProblem(array $item, string $today): ?string
    {
        return $this->restockProblem($this->planReturn($item, 1), $today);
    }

    /** Remember which batches a refund line came from and count the units against the sale line. */
    public function recordReturn(int $refundItemId, array $item, array $plan, string $now): void
    {
        foreach ($plan['entries'] as $entry) {
            $rowId = $entry['sale_item_batch_id'];
            if ($rowId === null && !empty($entry['legacy'])) {
                $ok = $this->db->table('sale_item_batches')->insert([
                    'sale_item_id' => (int) $item['id'], 'batch_id' => $entry['batch_id'], 'quantity' => $entry['row_quantity'],
                    'quantity_returned' => $entry['row_returned'] + $entry['quantity'], 'delivery_id' => $entry['delivery_id'],
                    'batch_no' => $entry['batch_no'], 'expiration_date' => $entry['expiration_date'], 'created_at' => $now,
                ]);
                if (!$ok) throw new RuntimeException('Could not save the return batch details.');
            } elseif ($rowId !== null) {
                $this->db->query('UPDATE sale_item_batches SET quantity_returned = quantity_returned + ? WHERE id = ? AND quantity_returned + ? <= quantity',
                    [$entry['quantity'], $rowId, $entry['quantity']]);
                if ($this->db->affectedRows() !== 1) throw new InvalidArgumentException('The returned quantity exceeds what was sold from this batch.');
            }
            if (!$this->db->table('refund_item_batches')->insert(['refund_item_id' => $refundItemId, 'batch_id' => $entry['batch_id'], 'quantity' => $entry['quantity'], 'created_at' => $now])) {
                throw new RuntimeException('Could not save the return batch details.');
            }
        }
    }

    /**
     * Put returned units back into the batches they came from.
     * Callers check restockProblem() first.
     */
    public function restock(array $bp, array $plan, string $today, int $userId, string $action, string $remarks, string $now): int
    {
        $bp = $this->reconcile($this->lockInventory((int) $bp['id']), $now);
        if ($problem = $this->restockProblem($plan, $today, true)) throw new InvalidArgumentException($problem);
        $running = (int) $bp['stock'];
        $total = 0;
        foreach ($plan['entries'] as $entry) {
            $qty = (int) $entry['quantity'];
            if ($running > self::MAX_STOCK - $qty) throw new InvalidArgumentException('The stock balance cannot accept this return. Ask an administrator to review it.');
            $this->changeBatchQty((int) $entry['batch_id'], $qty, $now);
            $this->log($bp, (int) $entry['batch_id'], $action, $qty, $running, $running + $qty, $remarks, $userId, $now);
            $running += $qty;
            $total += $qty;
        }
        $this->sync((int) $bp['id'], $now);
        return $total;
    }

    /**
     * Administrator sale correction: change how many units a line took from its original
     * batches. A positive difference takes more units, a negative one puts units back.
     */
    public function correctSold(array $bp, array $item, int $difference, string $today, int $userId, string $remarks, string $now): void
    {
        if ($difference === 0) return;
        $bp = $this->reconcile($this->lockInventory((int) $bp['id']), $now);
        $rows = $this->allocationRows($item, true);
        if (!$rows) throw new InvalidArgumentException(self::MSG_UNKNOWN_DELIVERY);
        $running = (int) $bp['stock'];
        $left = abs($difference);
        foreach ($rows as $row) {
            if ($left <= 0) break;
            $batch = $this->batch((int) $row['batch_id'], true);
            if (!$batch || !hash_equals((string) $row['delivery_id'], (string) $batch['delivery_id'])) throw new InvalidArgumentException(self::MSG_UNKNOWN_DELIVERY);
            if ($batch['expiration_date'] === null) throw new InvalidArgumentException(self::MSG_NO_EXPIRY);
            if ($batch['expiration_date'] <= $today) throw new InvalidArgumentException('Only unexpired stock from the original batch can be used to change a sold quantity.');
            if (!empty($row['legacy']) && (string) $row['expiration_date'] !== (string) $batch['expiration_date']) throw new InvalidArgumentException(self::MSG_UNKNOWN_DELIVERY);
            $room = $difference > 0 ? (int) $batch['quantity_remaining'] : (int) $row['quantity'] - (int) $row['quantity_returned'];
            $take = min($left, $room);
            if ($take <= 0) continue;
            $signed = $difference > 0 ? -$take : $take;
            $this->changeBatchQty((int) $batch['id'], $signed, $now);
            $rowQuantity = (int) $row['quantity'] + ($difference > 0 ? $take : -$take);
            if ($row['id'] === null) {
                $ok = $this->db->table('sale_item_batches')->insert(['sale_item_id' => (int) $item['id'], 'batch_id' => (int) $batch['id'], 'quantity' => $rowQuantity,
                    'quantity_returned' => (int) $row['quantity_returned'], 'delivery_id' => $row['delivery_id'], 'batch_no' => $row['batch_no'],
                    'expiration_date' => $row['expiration_date'], 'created_at' => $now]);
            } else {
                $ok = $this->db->table('sale_item_batches')->where('id', (int) $row['id'])->update(['quantity' => $rowQuantity]);
            }
            if (!$ok) throw new RuntimeException('Could not save the corrected batch details.');
            $this->log($bp, (int) $batch['id'], 'adjustment', $take, $running, $running + $signed, $remarks, $userId, $now);
            $running += $signed;
            $left -= $take;
        }
        if ($left > 0) {
            throw new InvalidArgumentException($difference > 0
                ? 'The original batch does not have enough unexpired stock for this larger quantity. Use a new sale for the extra units.'
                : self::MSG_UNKNOWN_DELIVERY);
        }
        $this->sync((int) $bp['id'], $now);
    }

    // ------------------------------------------------------------------
    // Administrator corrections
    // ------------------------------------------------------------------

    /** Fix a typo in a batch's lot number or expiry date. Quantities are unchanged. */
    public function correctDetails(int $batchId, ?string $batchNo, ?string $expiry, string $now): array
    {
        $batch = $this->batch($batchId);
        if (!$batch) throw new InvalidArgumentException('Batch not found.');
        // Same lock order as every other write: the inventory row first, then its batch.
        $this->lockInventory((int) $batch['branch_product_id']);
        $batch = $this->batch($batchId, true);
        if (!$batch) throw new InvalidArgumentException('Batch not found.');
        $batchNo = self::cleanBatchNo($batchNo);
        $expiry = self::cleanExpiry($expiry);
        if ($batchNo !== null) {
            $clash = $this->db->query('SELECT id FROM product_batches WHERE branch_product_id = ? AND batch_no = ? AND expiration_date <=> ? AND id <> ? LIMIT 1',
                [(int) $batch['branch_product_id'], $batchNo, $expiry, $batchId])->getRowArray();
            if ($clash) throw new InvalidArgumentException('Another batch of this product already uses that lot number and expiry date.');
        }
        $this->db->table('product_batches')->where('id', $batchId)->update(['batch_no' => $batchNo, 'expiration_date' => $expiry, 'updated_at' => $now]);
        $this->sync((int) $batch['branch_product_id'], $now);
        return ['before' => $batch, 'after' => $this->batch($batchId)];
    }
}
