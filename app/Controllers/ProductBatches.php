<?php

namespace App\Controllers;

use App\Libraries\StockBatches;

/**
 * Batches (lots) of one branch product: what was received, what is left and when it
 * expires. Administrators can correct a batch's lot number or expiry date.
 */
class ProductBatches extends BaseController
{
    private function inventory(int $id): ?array
    {
        $db = db_connect();
        $row = $db->table('branch_products bp')
            ->select('bp.*, p.product_name, p.sku, p.unit, b.branch_name')
            ->join('products p', 'p.id = bp.product_id')->join('branches b', 'b.id = bp.branch_id')
            ->where('bp.id', $id)->get()->getRowArray();
        if (!$row) return null;
        $role = (string) session('role');
        if ($role === 'cashier' && (int) $row['branch_id'] !== (int) session('branch_id')) return null;
        return in_array($role, ['admin', 'cashier'], true) ? $row : null;
    }

    public function index($id)
    {
        $row = $this->inventory((int) $id);
        if (!$row) {
            return redirect()->to(site_url('products'))->with('error', 'This inventory record is not available for your branch.');
        }
        $db = db_connect();
        $showAll = $this->request->getGet('all') === '1';
        $batches = (new StockBatches($db))->batches((int) $row['id'], !$showAll);
        $supplierIds = array_values(array_unique(array_filter(array_column($batches, 'supplier_id'))));
        $suppliers = $supplierIds ? array_column($db->table('suppliers')->whereIn('id', $supplierIds)->get()->getResultArray(), 'supplier_name', 'id') : [];

        return view('products/batches', [
            'row' => $row, 'batches' => $batches, 'showAll' => $showAll, 'suppliers' => $suppliers,
            'batchNumbers' => StockBatches::numbers($db, array_column($batches, 'id')),
            'canCorrect' => session('role') === 'admin',
        ]);
    }

    public function correct($batchId)
    {
        if (session('role') !== 'admin') {
            return redirect()->to(site_url('products'))->with('error', 'Only an administrator can correct batch details.');
        }
        $db = db_connect();
        $service = new StockBatches($db);
        $batch = $service->batch((int) $batchId);
        if (!$batch) {
            return redirect()->to(site_url('products'))->with('error', 'Batch not found.');
        }
        $back = site_url('products/batches/' . (int) $batch['branch_product_id']);
        $reason = trim((string) $this->request->getPost('reason'));
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 150) {
            return redirect()->to($back)->with('error', 'Enter a reason for the correction (3 to 150 characters).');
        }

        $db->transBegin();
        try {
            $result = $service->correctDetails((int) $batchId, (string) $this->request->getPost('batch_no'), (string) $this->request->getPost('expiration_date'), date('Y-m-d H:i:s'));
            $before = $result['before'];
            $after = $result['after'];
            $db->table('activity_logs')->insert([
                'user_id' => (int) session('user_id'),
                'activity' => 'Corrected batch #' . $after['id'] . ' of product #' . $after['product_id'] . ' in branch #' . $after['branch_id']
                    . ': lot ' . ($before['batch_no'] ?? 'none') . ' → ' . ($after['batch_no'] ?? 'none')
                    . '; expiry ' . ($before['expiration_date'] ?? 'none') . ' → ' . ($after['expiration_date'] ?? 'none') . '. Reason: ' . $reason,
                'log_time' => date('Y-m-d H:i:s'),
            ]);
            if ($db->transStatus() === false) throw new \RuntimeException('The correction could not be saved.');
            $db->transCommit();
        } catch (\InvalidArgumentException $error) {
            $db->transRollback();
            return redirect()->to($back)->with('error', $error->getMessage());
        } catch (\Throwable $error) {
            $db->transRollback();
            log_message('error', 'Batch correction failed: {message}', ['message' => $error->getMessage()]);
            return redirect()->to($back)->with('error', 'The correction could not be saved. Please try again.');
        }

        return redirect()->to($back)->with('success', 'Batch details corrected.');
    }
}
