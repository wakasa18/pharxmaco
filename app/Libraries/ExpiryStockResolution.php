<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;

final class ExpiryStockResolution
{
    public const ACTIONS = ['replace' => 'Replace with new stock', 'remove' => 'Dispose / Return to supplier', 'deactivate' => 'Deactivate in this branch'];

    public function __construct(private BaseConnection $db) {}

    public static function authorize(string $role, string $action): void
    {
        if (!in_array($role, ['admin', 'cashier'], true) || ($action === 'deactivate' && $role !== 'admin')) {
            throw new \DomainException('Only an administrator can deactivate a product in a branch.');
        }
        if (!isset(self::ACTIONS[$action])) throw new InvalidArgumentException('Select a valid expiry-stock action.');
    }

    public function inventory(int $id, string $role, int $branchId, bool $lock = false, ?int $batchId = null): array
    {
        $row = $this->db->query('SELECT * FROM branch_products WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''), [$id])->getRowArray();
        if (!$row || !in_array($role, ['admin', 'cashier'], true) || ($role === 'cashier' && ($branchId <= 0 || (int) $row['branch_id'] !== $branchId))) {
            throw new \DomainException('This inventory record is not available for your branch.');
        }
        $product = $this->db->query('SELECT * FROM products WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''), [$row['product_id']])->getRowArray();
        $branch = $this->db->table('branches')->where('id', $row['branch_id'])->get()->getRowArray();
        if (!$product || !$branch) throw new InvalidArgumentException('The product or branch record is missing.');
        $row['branch_deleted_at'] = $row['deleted_at'] ?? null;
        $row['branch_is_deleted'] = $row['is_deleted'] ?? 0;
        $row['branch_permanently_deleted_at'] = $row['permanently_deleted_at'] ?? null;
        $row['branch_is_permanently_deleted'] = $row['is_permanently_deleted'] ?? 0;
        unset($row['deleted_at'], $row['is_deleted'], $row['permanently_deleted_at'], $row['is_permanently_deleted']);
        // Expiry actions apply to one batch: the one named, otherwise the batch expiring first.
        $batchService = new StockBatches($this->db);
        $batches = $batchService->batches((int) $row['id'], true, $lock);
        $batch = null;
        foreach ($batches as $candidate) {
            if ($batchId === null || (int) $candidate['id'] === $batchId) { $batch = $candidate; break; }
        }
        if ($batchId !== null && !$batch) throw new InvalidArgumentException('That batch no longer has stock. Reopen the expiry report.');
        $row['batches'] = $batches;
        $row['batch'] = $batch;
        $row['batch_id'] = $batch['id'] ?? null;
        $row['batch_quantity'] = $batch['quantity_remaining'] ?? null;
        $row['batch_expiration_date'] = $batch['expiration_date'] ?? null;
        $row['batch_lot'] = $batch['batch_no'] ?? null;
        return $row + ['product_name' => $product['product_name'], 'sku' => $product['sku'], 'unit' => $product['unit'],
            'product_status' => $product['status'], 'deleted_at' => $product['deleted_at'], 'is_deleted' => $product['is_deleted'], 'permanently_deleted_at' => $product['permanently_deleted_at'], 'is_permanently_deleted' => $product['is_permanently_deleted'],
            'branch_name' => $branch['branch_name'], 'branch_status' => $branch['status']];
    }

    public static function revision(array $row): string
    {
        $values = [];
        foreach (['id','product_id','branch_id','stock','expiration_date','stock_delivery_id','status','updated_at','product_status','deleted_at','is_deleted','permanently_deleted_at','is_permanently_deleted','branch_deleted_at','branch_is_deleted','branch_permanently_deleted_at','branch_is_permanently_deleted','branch_status','batch_id','batch_quantity','batch_expiration_date','batch_lot'] as $key) $values[] = $row[$key] ?? null;
        $values[] = array_map(static fn ($b) => [$b['id'], $b['quantity_remaining'], $b['expiration_date'], $b['batch_no']], $row['batches'] ?? []);
        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
    }

    private function quantity($value): int
    {
        if (!is_scalar($value) || filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value <= 0 || (int) $value > 1000000) {
            throw new InvalidArgumentException('Enter a whole-number quantity from 1 to 1,000,000.');
        }
        return (int) $value;
    }

    public function resolve(int $id, string $action, array $input, string $role, int $branchId, int $userId, string $token, string $revision): bool
    {
        self::authorize($role, $action);
        if ($userId <= 0 || !preg_match('/^[a-f0-9]{48}$/D', $token)) throw new InvalidArgumentException('This action has expired. Reopen the expiry record.');
        $reason = is_string($input['reason'] ?? null) ? trim($input['reason']) : '';
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 100) throw new InvalidArgumentException('Enter a reason between 3 and 100 characters.');
        if (($input['confirmed'] ?? '') !== '1') throw new InvalidArgumentException('Confirm the stock action before saving.');
        $marker = '[ER:' . substr(hash('sha256', $id . ':' . $userId . ':' . $action . ':' . $token), 0, 32) . ']';
        $this->db->transBegin();
        try {
            $batchId = filter_var($input['batch_id'] ?? null, FILTER_VALIDATE_INT);
            $row = $this->inventory($id, $role, $branchId, true, $batchId === false || $batchId < 1 ? null : (int) $batchId);
            // The row lock and durable log marker serialize repeated submissions.
            if ($this->db->table('stock_logs')->where('product_id', $row['product_id'])->where('branch_id', $row['branch_id'])->like('remarks', $marker)->countAllResults()) {
                $this->db->transCommit();
                return false;
            }
            if (!hash_equals(self::revision($row), $revision)) throw new InvalidArgumentException('Stock, expiry or status changed while this form was open. Reopen the record and review the latest values.');
            if ($row['status'] !== 'active' || $row['product_status'] !== 'active' || $row['branch_status'] !== 'active' || $row['deleted_at'] !== null || $row['branch_deleted_at'] !== null || !empty($row['is_deleted']) || !empty($row['branch_is_deleted']) || !empty($row['is_permanently_deleted']) || !empty($row['branch_is_permanently_deleted'])) {
                throw new InvalidArgumentException('Only active inventory, products and branches can be changed here.');
            }
            $oldStock = (int) $row['stock'];
            if ($oldStock < 0) throw new InvalidArgumentException('The current stock count needs administrator review.');
            $batch = $row['batch'];
            $expiry = ($batch['expiration_date'] ?? null) ?: 'not recorded';
            $lot = ($batch['batch_no'] ?? null) !== null ? ' ' . $batch['batch_no'] : '';
            $batchLabel = $batch ? 'batch' . ($lot !== '' ? $lot : ' #' . $batch['id']) : 'no batch';
            $now = date('Y-m-d H:i:s');
            $newStock = $oldStock;
            $newExpiryLabel = $expiry;
            $batches = new StockBatches($this->db);
            if ($action === 'deactivate') {
                $this->write($this->db->table('branch_products')->where('id', $id)->update(['status' => 'inactive', 'updated_at' => $now]));
                $this->write($this->db->table('stock_logs')->insert(['product_id' => $row['product_id'], 'branch_id' => $row['branch_id'], 'user_id' => $userId,
                    'action_type' => 'adjustment', 'quantity' => 0, 'previous_stock' => $oldStock, 'new_stock' => $oldStock,
                    'remarks' => 'Expiry resolution: Deactivated in branch; stock retained. ' . $reason . ' ' . $marker, 'created_at' => $now]));
            } else {
                $outcome = $input['outcome'] ?? '';
                if (!in_array($outcome, ['disposed', 'supplier_return'], true)) throw new InvalidArgumentException('Select Disposed or Returned to supplier for the removed stock.');
                $outcomeLabel = $outcome === 'disposed' ? 'Disposed' : 'Returned to supplier';
                if ($action === 'remove') {
                    if (!$batch) throw new InvalidArgumentException('There is no batch with stock to remove.');
                    $quantity = $this->quantity($input['quantity'] ?? null);
                    if ($quantity > (int) $batch['quantity_remaining']) throw new InvalidArgumentException('The removal quantity exceeds the stock in this batch.');
                    $batches->remove($row, $quantity, $userId, 'Expiry resolution: ' . $outcomeLabel . '; ' . $batchLabel . '; expiry ' . $expiry . '. ' . $reason . ' ' . $marker, $now, (int) $batch['id']);
                } else {
                    // Replacement removes the whole old batch, then receives the new stock as its own batch.
                    $newQuantity = $this->quantity($input['new_quantity'] ?? null);
                    $newExpiry = is_string($input['new_expiry'] ?? null) ? trim($input['new_expiry']) : '';
                    if ($newExpiry === '') throw new InvalidArgumentException('Enter the expiration date for the new stock.');
                    // Replacement is new stock even if its date matches the old delivery.
                    ExpiryDatePolicy::assertAllowed($newExpiry);
                    $newLot = StockBatches::cleanBatchNo(is_string($input['new_batch_no'] ?? null) ? $input['new_batch_no'] : null);
                    if ($batch) {
                        $batches->remove($row, (int) $batch['quantity_remaining'], $userId, 'Expiry resolution: Replaced old stock: ' . $outcomeLabel . '; ' . $batchLabel . '; expiry ' . $expiry . '. ' . $reason . ' ' . $marker, $now, (int) $batch['id']);
                    }
                    $batches->receive($row, $newQuantity, $newExpiry, $newLot, $userId, 'Expiry resolution: Replacement received; expiry ' . $expiry . ' -> ' . $newExpiry . '. ' . $reason . ' ' . $marker, $now,
                        ['source' => 'replacement', 'supplier_id' => null]);
                    $newExpiryLabel = $newExpiry;
                }
                $newStock = (int) ($this->db->query('SELECT stock FROM branch_products WHERE id = ?', [$id])->getRowArray()['stock'] ?? 0);
                $sum = $this->db->table('branch_products')->selectSum('stock', 'total')->where('product_id', $row['product_id'])->get()->getRowArray();
                $this->write($this->db->table('products')->where('id', $row['product_id'])->update(['stock' => (int) $sum['total'], 'updated_at' => $now]));
            }
            $this->write($this->db->table('activity_logs')->insert(['user_id' => $userId,
                'activity' => 'Expiry resolution: ' . self::ACTIONS[$action] . ' for product #' . $row['product_id'] . ' in branch #' . $row['branch_id']
                    . '. Stock ' . $oldStock . ' -> ' . $newStock . '; ' . $batchLabel . ' expiry ' . $expiry . ' -> ' . $newExpiryLabel . '. ' . $reason . ' ' . $marker,
                'log_time' => $now]));
            if (!$this->db->transStatus()) throw new \RuntimeException('Expiry resolution could not be saved.');
            $this->db->transCommit();
            return true;
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }

    private function write(bool $ok): void
    {
        if (!$ok) throw new \RuntimeException('Expiry resolution could not be saved.');
    }

    public function history(array $row): array
    {
        return $this->db->table('stock_logs l')->select('l.*, u.full_name')->join('users u', 'u.id = l.user_id', 'left')
            ->where('l.product_id', $row['product_id'])->where('l.branch_id', $row['branch_id'])
            ->like('l.remarks', 'Expiry resolution:', 'after')->orderBy('l.id', 'DESC')->get(50)->getResultArray();
    }
}
