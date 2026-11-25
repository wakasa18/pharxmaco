<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SalesModel;
use App\Models\SaleItemModel;
use App\Models\ActivityLogModel;
use App\Models\StockLogModel;
use App\Models\BranchProductModel;

class SaleCorrection extends BaseController
{
    protected SalesModel $salesModel;
    protected SaleItemModel $saleItemModel;
    protected ActivityLogModel $activityLogModel;
    protected StockLogModel $stockLogModel;
    protected BranchProductModel $branchProductModel;
    protected $db;

    public function __construct()
    {
        $this->salesModel = new SalesModel();
        $this->saleItemModel = new SaleItemModel();
        $this->activityLogModel = new ActivityLogModel();
        $this->stockLogModel = new StockLogModel();
        $this->branchProductModel = new BranchProductModel();
        $this->db = \Config\Database::connect();
    }

    public function edit(int $saleId)
    {
        if (session('role') !== 'admin') return redirect()->to(site_url('cashier/sales/history'))->with('error', 'Only administrators can correct sales.');
        $sale = $this->getSaleWithDetails($saleId);
        if (!$sale) {
            return redirect()->to(site_url('cashier/sales/history'))->with('error', 'Sale record not found.');
        }
        if (!$this->canCorrect($sale)) {
            return redirect()->to(site_url('cashier/sales/history'))
                ->with('error', 'Only completed sales without refunds or exchange links can be corrected.');
        }

        return $this->renderCorrection($sale);
    }

    public function update(int $saleId)
    {
        if (session('role') !== 'admin') return redirect()->to(site_url('cashier/sales/history'))->with('error', 'Only administrators can correct sales.');
        $sale = $this->getSaleWithDetails($saleId);
        if (!$sale) {
            return redirect()->to(site_url('cashier/sales/history'))->with('error', 'Sale record not found.');
        }
        if (!$this->canCorrect($sale)) {
            return redirect()->to(site_url('cashier/sales/history'))
                ->with('error', 'Only completed sales without refunds or exchange links can be corrected.');
        }

        foreach (['payment_method', 'reference_no'] as $field) {
            $value = $this->request->getPost($field);
            if ($value !== null && !is_string($value)) {
                return $this->renderCorrection($sale, null, 'Enter valid payment details and a text reference number.');
            }
        }
        $rules = [
            'correction_reason' => 'required|min_length[5]|max_length[500]',
            'notes' => 'permit_empty|max_length[500]',
            'payment_method' => 'required|in_list[cash,gcash,card]',
            'reference_no' => 'permit_empty|max_length[100]',
        ];
        if (!$this->validateData($this->request->getPost(), $rules)) {
            return $this->renderCorrection($sale, $this->validator);
        }

        $postedItems = $this->request->getPost('items');
        if (!is_array($postedItems)) {
            $postedItems = [];
        }
        foreach ($postedItems as $qty) {
            if (!is_numeric($qty) || filter_var($qty, FILTER_VALIDATE_INT) === false || (int) $qty < 0 || (int) $qty > 100000) {
                return $this->renderCorrection($sale, null, 'All corrected quantities must be whole numbers between 0 and 100,000.');
            }
        }

        $newPaymentMethod = trim((string) $this->request->getPost('payment_method'));
        $newReferenceNo = trim((string) ($this->request->getPost('reference_no') ?? ''));
        if ($newPaymentMethod === 'cash') {
            $newReferenceNo = '';
        }

        try {
            $this->db->transStart();
            $cashierService = new \App\Libraries\CashierShift($this->db);
            $cashierShift = null;
            if ($cashierService->enabled()) {
                $financialChange = $newPaymentMethod !== $sale['payment_method'];
                foreach ($this->saleItemModel->where('sale_id', $saleId)->findAll() as $item) {
                    if (isset($postedItems[$item['id']]) && (int) $postedItems[$item['id']] !== (int) $item['quantity']) $financialChange = true;
                }
                if ($financialChange) $cashierShift = $cashierService->requireActive((int) session('user_id'), (int) $sale['branch_id']);
            }

            // Lock the sale so a refund cannot be processed while an administrator
            // is correcting the same transaction in another browser session.
            $lockedSale = $this->db->query(
                'SELECT * FROM sales WHERE id = ? FOR UPDATE',
                [$saleId]
            )->getRowArray();
            $hasRefunds = $this->db->table('refund_items')->where('sale_id', $saleId)->countAllResults() > 0;
            if (!$lockedSale || $lockedSale['status'] !== 'completed' || $hasRefunds || \App\Libraries\ExchangeRecord::identity($lockedSale)) {
                $this->db->transRollback();
                return redirect()->to(site_url('cashier/sales/history'))
                    ->with('error', 'This sale changed while the correction page was open. Review its latest status before trying again.');
            }

            $sale = array_replace($sale, $lockedSale);
            $currentItems = $this->db->query(
                'SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id FOR UPDATE', [$saleId]
            )->getResultArray();
            if (!hash_equals(\App\Libraries\SaleRevision::fingerprint($sale, $currentItems), (string) $this->request->getPost('sale_revision'))) {
                $this->db->transRollback();
                return redirect()->to(site_url('admin/sale-correction/' . $saleId))
                    ->with('error', 'This sale was changed by another request. Review the latest values before saving again.');
            }
            $rejectCorrection = function (array $sale, $validation, string $message) {
                $this->db->transRollback();
                return $this->renderCorrection($sale, $validation, $message);
            };
            if ($cashierShift && $newPaymentMethod !== $sale['payment_method']) {
                $sameShift = $this->db->table('cashier_events')->where('sale_id', $saleId)
                    ->where('event_type', 'sale')->where('shift_id', $cashierShift['id'])->countAllResults();
                if (!$sameShift) return $rejectCorrection($sale, null, 'Payment method can only be corrected within the sale\'s original open shift. For older sales, record a note or process a refund and new sale so reconciled cash is preserved.');
            }

            $pricing = new \App\Libraries\SaleCorrectionPricing($this->db);
            $hasDiscount = \App\Libraries\DiscountPolicy::hasDiscount($sale, $currentItems);
            $discountContext = $pricing->context($sale, $currentItems, true);
            try {
                $calculation = $pricing->calculate($sale, $currentItems, $postedItems, $discountContext);
            } catch (\InvalidArgumentException $error) {
                return $rejectCorrection($sale, null, $error->getMessage());
            }
            if ($calculation['changed'] && $hasDiscount && !hash_equals($discountContext['revision'], (string) $this->request->getPost('discount_revision'))) {
                return $rejectCorrection($sale, null, 'The discount changed while this page was open. Review the latest quantities and payment difference before saving again.');
            }
            $plans = $calculation['plans'];
            $newTotal = $calculation['total'];
            $newDiscountAmount = $calculation['discount'];
            $finalTotal = $calculation['final'];
            $difference = $calculation['difference'];
            $referenceUnchanged = $newPaymentMethod === $sale['payment_method']
                && $newReferenceNo === trim((string) ($sale['reference_no'] ?? ''));
            if (!$referenceUnchanged || $difference !== 0) {
                try {
                    $newReferenceNo = \App\Libraries\PaymentReference::assertAllowed($newPaymentMethod, $newReferenceNo);
                } catch (\InvalidArgumentException $error) {
                    return $rejectCorrection($sale, null, $error->getMessage());
                }
            }
            if ($difference !== 0) {
                $confirmedDifference = $this->request->getPost('settlement_difference');
                if ($this->request->getPost('settlement_confirmed') !== '1' || filter_var($confirmedDifference, FILTER_VALIDATE_INT) === false || (int) $confirmedDifference !== $difference) {
                    return $rejectCorrection($sale, null, 'Confirm the exact payment difference after collecting the additional payment or paying back the customer.');
                }
                if ($newPaymentMethod !== 'cash' && $newReferenceNo === '') {
                    return $rejectCorrection($sale, null, 'Enter the GCash or card reference for this payment difference.');
                }
            }
            $newAmountPaid = (\App\Libraries\DiscountPolicy::cents((float) $sale['amount_paid']) + $difference) / 100;
            if ($calculation['changed'] && (\App\Libraries\DiscountPolicy::cents((float) $sale['amount_paid']) - \App\Libraries\DiscountPolicy::cents((float) $sale['change_amount']) !== \App\Libraries\DiscountPolicy::cents((float) $sale['final_total'])
                || $newAmountPaid < $finalTotal || $newAmountPaid > 99999999.99)) {
                return $rejectCorrection($sale, null, 'The recorded payment and change do not match this sale. Quantities cannot be corrected until those records are reviewed.');
            }
            foreach ($plans as &$plan) {
                $item = $plan['item'];
                $qtyDiff = $plan['qty_diff'];
                $branchProduct = null;
                if ($qtyDiff !== 0) {
                    $branchProduct = $this->branchProductModel
                        ->where('product_id', $item['product_id'])
                        ->where('branch_id', $sale['branch_id'])
                        ->first();

                    if (!$branchProduct) {
                        return $rejectCorrection($sale, null, 'The branch inventory record for ' . $item['product_name_snapshot'] . ' was not found.');
                    }
                    if ($qtyDiff > 0 && (int) $branchProduct['stock'] < $qtyDiff) {
                        return $rejectCorrection(
                            $sale,
                            null,
                            'Not enough stock to increase ' . $item['product_name_snapshot'] . '. Available stock: ' . (int) $branchProduct['stock'] . '.'
                        );
                    }
                }

                $plan['branch_product'] = $branchProduct;
            }
            unset($plan);

            $correctionReason = trim((string) $this->request->getPost('correction_reason'));
            $newNotes = trim((string) ($this->request->getPost('notes') ?? ''));
            $userId = (int) session('user_id');
            $changes = [];
            $changedProductIds = [];
            if ($calculation['changed']) {
                $changes[] = 'Total: PHP ' . number_format((float) $sale['final_total'], 2, '.', '') . ' -> PHP ' . number_format($finalTotal, 2, '.', '');
                $changes[] = 'Discount: PHP ' . number_format((float) $sale['discount_amount'], 2, '.', '') . ' -> PHP ' . number_format($newDiscountAmount, 2, '.', '');
            }
            if ($difference !== 0) {
                $changes[] = ($difference > 0 ? 'Additional payment collected: PHP ' : 'Paid back to customer: PHP ') . number_format(abs($difference) / 100, 2, '.', '') . ' via ' . $newPaymentMethod
                    . ($newReferenceNo !== '' ? ' (reference ' . $newReferenceNo . ')' : '');
                $changes[] = 'Recorded tender: PHP ' . number_format((float) $sale['amount_paid'], 2, '.', '') . ' -> PHP ' . number_format($newAmountPaid, 2, '.', '') . '; original change retained';
            }

            if ((string) ($sale['notes'] ?? '') !== $newNotes) {
                $changes[] = 'Notes changed';
            }
            if ($sale['payment_method'] !== $newPaymentMethod) {
                $changes[] = 'Payment method: ' . $sale['payment_method'] . ' → ' . $newPaymentMethod;
            }
            if ((string) ($sale['reference_no'] ?? '') !== $newReferenceNo) {
                $changes[] = 'Payment reference updated';
            }

            foreach ($plans as $plan) {
                $item = $plan['item'];
                if ($plan['qty_diff'] !== 0) {
                    $branchProduct = $this->db->query(
                        'SELECT * FROM branch_products WHERE id = ? AND branch_id = ? FOR UPDATE',
                        [(int) $plan['branch_product']['id'], (int) $sale['branch_id']]
                    )->getRowArray();
                    if (!$branchProduct) {
                        $this->db->transRollback();
                        return $this->renderCorrection($sale, null, 'The branch inventory record for ' . $item['product_name_snapshot'] . ' is no longer available.');
                    }
                    if ((int)$plan['qty_diff'] > 0 && (!empty($branchProduct['deleted_at']) || !empty($branchProduct['is_deleted']) || !empty($branchProduct['is_permanently_deleted']))) {
                        $this->db->transRollback();
                        return $this->renderCorrection($sale, null, 'This product is in branch Trash. Restore it before increasing the sold quantity.');
                    }
                    if ((int) $plan['qty_diff'] > 0) {
                        $product = $this->db->query('SELECT * FROM products WHERE id = ? FOR UPDATE', [(int) $item['product_id']])->getRowArray();
                        $branch = $this->db->table('branches')->where('id', $sale['branch_id'])->get()->getRowArray();
                        if (!$product || $product['status'] !== 'active' || !empty($product['deleted_at']) || !empty($product['is_deleted']) || !empty($product['is_permanently_deleted'])
                            || $branchProduct['status'] !== 'active' || ($branch['status'] ?? '') !== 'active') {
                            return $rejectCorrection($sale, null, 'Only active, unexpired stock in an active branch can be used to increase a sold quantity.');
                        }
                    }
                    try {
                        // Both increases and decreases stay within the batches the line was originally
                        // sold from, and only while those batches are unexpired. Otherwise use a refund and a new sale.
                        (new \App\Libraries\StockBatches($this->db))->correctSold(
                            $branchProduct, $item, (int) $plan['qty_diff'], date('Y-m-d'), $userId,
                            'Sale correction ' . $sale['invoice_no'] . ': qty ' . $plan['old_qty'] . ' → ' . $plan['new_qty'] . '. Reason: ' . $correctionReason,
                            date('Y-m-d H:i:s')
                        );
                    } catch (\InvalidArgumentException $error) {
                        return $rejectCorrection($sale, null, $error->getMessage());
                    }
                    $changedProductIds[(int) $item['product_id']] = true;
                    $changes[] = '"' . $item['product_name_snapshot'] . '" qty: ' . $plan['old_qty'] . ' → ' . $plan['new_qty'];
                }

                if (!$this->saleItemModel->update((int) $item['id'], [
                    'quantity' => $plan['new_qty'],
                    'subtotal' => $plan['subtotal'],
                    'discount_applied' => $plan['discount'],
                    'profit' => $plan['profit'],
                ])) throw new \RuntimeException('Could not save corrected item.');
            }

            foreach (array_keys($changedProductIds) as $productId) {
                $this->syncMasterStock((int) $productId);
            }

            if (!$this->salesModel->update($saleId, [
                'notes' => $newNotes !== '' ? $newNotes : null,
                'payment_method' => $newPaymentMethod,
                'reference_no' => $newReferenceNo !== '' ? $newReferenceNo : null,
                'total_amount' => $newTotal,
                'discount_amount' => $newDiscountAmount,
                'final_total' => $finalTotal,
                'amount_paid' => $newAmountPaid,
                'change_amount' => (float) $sale['change_amount'],
                'updated_at' => date('Y-m-d H:i:s'),
            ])) throw new \RuntimeException('Could not save corrected sale.');

            $cashierService->recordCorrection($cashierShift, $sale, $currentItems, $plans, $finalTotal, $newPaymentMethod, $correctionReason);
            $changeSummary = $changes ? implode('; ', $changes) : 'No value changed (reason recorded only)';
            if (!$this->activityLogModel->insert([
                'user_id' => $userId,
                'activity' => 'Sale correction: INV#' . $sale['invoice_no'] . ' | ' . $changeSummary . ' | Reason: ' . $correctionReason,
                'log_time' => date('Y-m-d H:i:s'),
            ])) throw new \RuntimeException('Could not record correction audit.');

            $this->db->transComplete();
            if ($this->db->transStatus() === false) throw new \RuntimeException('Correction transaction failed.');

            return redirect()->to(site_url('admin/sale-correction/' . $saleId))
                ->with('success', 'Sale corrected and recorded in the activity log.');
        } catch (\Throwable $error) {
            $this->db->transRollback();
            if ($error instanceof \InvalidArgumentException) return $this->renderCorrection($sale, null, $error->getMessage());
            log_message('error', 'Sale correction failed for sale {id}: {error}', ['id'=>$saleId, 'error'=>$error->getMessage()]);
            return redirect()->to(site_url('admin/sale-correction/' . $saleId))->withInput()->with('error', 'Correction failed. No changes were saved.');
        }
    }

    private function renderCorrection(array $sale, $validation = null, ?string $itemError = null)
    {
        $items = $this->saleItemModel->where('sale_id', $sale['id'])->findAll();
        return view('admin/sale_correction/edit', [
            'sale' => $sale,
            'items' => $items,
            'discountContext' => (new \App\Libraries\SaleCorrectionPricing($this->db))->context($sale, $items),
            'formInput' => $this->request->getPost(),
            'correctionHistory' => $this->getCorrectionHistory($sale['invoice_no']),
            'validation' => $validation ?? \Config\Services::validation(),
            'itemError' => $itemError,
            'shiftTrackingEnabled' => (new \App\Libraries\CashierShift($this->db))->enabled(),
        ]);
    }

    private function canCorrect(array $sale): bool
    {
        if (\App\Libraries\ExchangeRecord::identity($sale)) return false;
        if (($sale['status'] ?? '') !== 'completed' || empty($sale['id'])) {
            return false;
        }

        return $this->db->table('refund_items')
            ->where('sale_id', (int) $sale['id'])
            ->countAllResults() === 0;
    }

    private function syncMasterStock(int $productId): void
    {
        $row = $this->db->table('branch_products')
            ->selectSum('stock', 'total_stock')
            ->where('product_id', $productId)
            ->get()
            ->getRowArray();

        if (!$this->db->table('products')->where('id', $productId)->update([
            'stock' => (int) ($row['total_stock'] ?? 0),
            'updated_at' => date('Y-m-d H:i:s'),
        ])) throw new \RuntimeException('Could not synchronize correction stock.');
    }

    private function getSaleWithDetails(int $saleId): ?array
    {
        return $this->salesModel
            ->select('sales.*, users.full_name, branches.branch_name')
            ->join('users', 'users.id = sales.user_id', 'left')
            ->join('branches', 'branches.id = sales.branch_id', 'left')
            ->where('sales.id', $saleId)
            ->first();
    }

    private function getCorrectionHistory(string $invoiceNo): array
    {
        return $this->activityLogModel
            ->select('activity_logs.*, users.full_name, users.username')
            ->join('users', 'users.id = activity_logs.user_id', 'left')
            ->like('activity_logs.activity', 'Sale correction: INV#' . $invoiceNo)
            ->orderBy('activity_logs.id', 'DESC')
            ->findAll();
    }
}
