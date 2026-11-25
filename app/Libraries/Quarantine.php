<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;

final class Quarantine
{
    public const OUTCOMES = ['released' => 'Released to stock', 'damaged' => 'Marked damaged', 'expired' => 'Marked expired'];

    public function __construct(private BaseConnection $db) {}

    public function ready(): bool
    {
        return $this->db->tableExists('quarantine_resolutions');
    }

    private function authorize(int $userId): void
    {
        $user = $this->db->table('users')->where('id', $userId)->get()->getRowArray();
        if (!$user || $user['role'] !== 'admin' || $user['status'] !== 'active') {
            throw new \DomainException('Only an active administrator can inspect quarantined returns.');
        }
    }

    public function records(int $userId, string $status = 'pending', int $branch = 0): array
    {
        $branch = ReturnRecordAccess::scope($this->db, $userId, $branch)['branch_id'];
        if ($status !== 'all' && $status !== 'pending' && !isset(self::OUTCOMES[$status])) throw new InvalidArgumentException('Choose a valid inspection status.');
        $builder = $this->db->table('refund_items r')
            ->select('r.*, s.branch_id, s.invoice_no, b.branch_name, p.sku, p.unit, q.outcome, q.inspected_at, u.full_name AS inspector')
            ->join('sales s', 's.id = r.sale_id')->join('branches b', 'b.id = s.branch_id', 'left')
            ->join('products p', 'p.id = r.product_id', 'left')
            ->join('quarantine_resolutions q', 'q.refund_item_id = r.id', 'left')
            ->join('users u', 'u.id = q.inspected_by', 'left')->where('r.return_condition', 'quarantined');
        if ($status === 'pending') $builder->where('q.id', null);
        elseif ($status !== 'all') $builder->where('q.outcome', $status);
        if ($branch > 0) $builder->where('s.branch_id', $branch);
        return $builder->orderBy('r.id', 'DESC')->get()->getResultArray();
    }

    public function item(int $id, int $userId, bool $lock = false): array
    {
        $scope = ReturnRecordAccess::scope($this->db, $userId);
        $suffix = $lock ? ' FOR UPDATE' : '';
        $refund = $this->db->query('SELECT * FROM refund_items WHERE id = ?' . $suffix, [$id])->getRowArray();
        if (!$refund || $refund['return_condition'] !== 'quarantined') throw new InvalidArgumentException('Quarantined return not found.');
        $saleQuery = $this->db->table('sales')->where('id', $refund['sale_id']);
        if (!$scope['can_view_all_branches']) $saleQuery->where('branch_id', $scope['branch_id']);
        $sale = $saleQuery->get()->getRowArray();
        if (!$sale) throw new InvalidArgumentException('Quarantined return not found.');
        $item = $this->db->query('SELECT * FROM sale_items WHERE id = ?' . $suffix, [$refund['sale_item_id']])->getRowArray();
        if (!$sale || !$item || (int) $item['sale_id'] !== (int) $sale['id'] || (int) $item['product_id'] !== (int) $refund['product_id']) {
            throw new InvalidArgumentException('The original sale and return records do not match. Ask for a data review.');
        }
        $inventory = $this->db->query('SELECT * FROM branch_products WHERE product_id = ? AND branch_id = ?' . $suffix,
            [$refund['product_id'], $sale['branch_id']])->getRowArray();
        $product = $this->db->query('SELECT * FROM products WHERE id = ?' . $suffix, [$refund['product_id']])->getRowArray();
        $branch = $this->db->query('SELECT * FROM branches WHERE id = ?' . $suffix, [$sale['branch_id']])->getRowArray();
        $resolutionQuery = $this->db->table('quarantine_resolutions q')->select('q.*, u.full_name AS inspector')
            ->join('users u', 'u.id = q.inspected_by', 'left')->where('q.refund_item_id', $id)->getCompiledSelect();
        $resolution = $this->db->query($resolutionQuery . $suffix)->getRowArray();
        return compact('refund', 'sale', 'item', 'inventory', 'product', 'branch', 'resolution');
    }

    public static function revision(array $record): string
    {
        unset($record['resolution']);
        return hash('sha256', json_encode($record, JSON_THROW_ON_ERROR));
    }

    private static function active(?array $record): bool
    {
        return $record && ($record['status'] ?? '') === 'active' && empty($record['deleted_at'])
            && empty($record['is_deleted']) && empty($record['permanently_deleted_at']) && empty($record['is_permanently_deleted']);
    }

    public static function releaseProblem(array $record, ?string $today = null, ?BaseConnection $db = null): ?string
    {
        if (!self::active($record['inventory']) || !self::active($record['product']) || !self::active($record['branch'])) {
            return 'Release requires an active product, branch and branch inventory record.';
        }
        $quantity = (int) $record['refund']['quantity_refunded'];
        if ($quantity < 1 || $quantity > (int) $record['item']['quantity']) return 'The returned quantity is invalid. Ask for a data review.';
        if ((int) $record['inventory']['stock'] < 0 || (int) $record['inventory']['stock'] + $quantity > 2147483647) {
            return 'The available stock balance cannot accept this quantity.';
        }
        // The units go back to the batches they were originally sold from.
        $batches = new StockBatches($db ?? db_connect());
        return $batches->restockProblem($batches->refundPlan($record['refund'], $record['item']), $today ?? date('Y-m-d'));
    }

    /** Resolve the entire returned line once. Original refund/financial records remain immutable. */
    public function resolve(int $id, int $userId, array $input, string $revision): bool
    {
        $this->authorize($userId);
        $outcome = $input['outcome'] ?? null;
        $note = is_string($input['note'] ?? null) ? trim($input['note']) : '';
        if (!is_string($outcome) || !isset(self::OUTCOMES[$outcome])) throw new InvalidArgumentException('Choose an inspection outcome.');
        if (mb_strlen($note) < 3 || mb_strlen($note) > 500 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $note)) {
            throw new InvalidArgumentException('Enter an inspection note between 3 and 500 characters.');
        }
        if (($input['confirmed'] ?? null) !== '1') throw new InvalidArgumentException('Confirm that you inspected all units in this return.');
        if (!$this->db->transBegin()) throw new \RuntimeException('Could not start inspection transaction.');
        try {
            $record = $this->item($id, $userId, true);
            // Refund row lock serializes competing inspectors; UNIQUE also guards external writers.
            if ($record['resolution']) { $this->db->transCommit(); return false; }
            if (!hash_equals(self::revision($record), $revision)) throw new InvalidArgumentException('The return or stock changed. Review the latest details before confirming again.');
            $quantity = (int) $record['refund']['quantity_refunded'];
            if ($quantity < 1 || $quantity > (int) $record['item']['quantity']) throw new InvalidArgumentException('The returned quantity is invalid. Ask for a data review.');
            $before = $after = $logId = null;
            $now = date('Y-m-d H:i:s');
            if ($outcome === 'released') {
                if ($problem = self::releaseProblem($record, null, $this->db)) throw new InvalidArgumentException($problem);
                $before = (int) $record['inventory']['stock'];
                $after = $before + $quantity;
                $batches = new StockBatches($this->db);
                $batches->restock($record['inventory'], $batches->refundPlan($record['refund'], $record['item'], true), date('Y-m-d'), $userId, 'returned',
                    'Quarantine release Q-' . $id . '; invoice ' . $record['sale']['invoice_no'] . '. Inspection: ' . $note, $now);
                $logId = $batches->lastLogId();
                // Current locking reads avoid an older REPEATABLE READ snapshot after waiting for another writer.
                $balances = $this->db->query('SELECT stock FROM branch_products WHERE product_id = ? FOR UPDATE', [$record['refund']['product_id']])->getResultArray();
                $total = array_sum(array_map(static fn ($row) => (int) $row['stock'], $balances));
                if ($total > 2147483647) throw new InvalidArgumentException('The product stock total exceeds the supported balance.');
                $this->write('products', ['stock' => $total, 'updated_at' => $now], (int) $record['refund']['product_id']);
            }
            $this->write('quarantine_resolutions', ['refund_item_id' => $id, 'outcome' => $outcome, 'quantity' => $quantity,
                'inspected_by' => $userId, 'inspected_at' => $now, 'note' => $note, 'stock_log_id' => $logId,
                'previous_stock' => $before, 'new_stock' => $after, 'snapshot_json' => json_encode([
                    'product_id' => $record['refund']['product_id'], 'product_name' => $record['refund']['product_name_snapshot'],
                    'branch_id' => $record['sale']['branch_id'], 'branch_name' => $record['branch']['branch_name'] ?? '',
                    'sale_id' => $record['sale']['id'], 'invoice' => $record['sale']['invoice_no'],
                    'original_delivery' => $record['item']['stock_delivery_id'], 'original_expiry' => $record['item']['expiration_date_at_sale'],
                    'inventory_id' => $record['inventory']['id'] ?? null, 'revision' => $revision,
                ], JSON_THROW_ON_ERROR)]);
            $this->write('activity_logs', ['user_id' => $userId, 'activity' => 'Quarantine Q-' . $id . ': ' . self::OUTCOMES[$outcome]
                . '; ' . $quantity . ' unit(s); invoice ' . $record['sale']['invoice_no'] . '; inspection: ' . $note, 'log_time' => $now]);
            if (!$this->db->transStatus() || !$this->db->transCommit()) throw new \RuntimeException('Inspection transaction failed.');
            return true;
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }

    private function write(string $table, array $values, ?int $id = null): void
    {
        $builder = $this->db->table($table);
        if (!($id === null ? $builder->insert($values) : $builder->where('id', $id)->update($values))) throw new \RuntimeException('Could not save ' . $table . '.');
    }
}
