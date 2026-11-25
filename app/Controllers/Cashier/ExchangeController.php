<?php

namespace App\Controllers\Cashier;

use App\Controllers\BaseController;
use App\Libraries\ProductExchange;

class ExchangeController extends BaseController
{
    public function form(int $saleId)
    {
        $draft = session('exchange_' . $saleId);
        return $this->renderForm($saleId, is_array($draft) ? ($draft['input'] ?? []) : []);
    }

    private function renderForm(int $saleId, array $input = [], ?string $error = null)
    {
        $db = db_connect();
        $service = new ProductExchange($db);
        try {
            $sale = $service->sale($saleId, (int) session('branch_id'), (string) session('role'));
            if (\App\Libraries\StatutoryDiscount::forSale($sale)) {
                return redirect()->to(site_url('cashier/sales/refund-form/' . $saleId))
                    ->with('error', 'Refund the returned items, then make a new POS sale to verify Senior Citizen/PWD replacement eligibility and beneficiary documents.');
            }
            if (!in_array($sale['status'], ['completed', 'partially_refunded'], true)) throw new \InvalidArgumentException('This sale has no items available for exchange.');
            $items = $service->remaining($sale);
            $batchService = new \App\Libraries\StockBatches($db);
            foreach ($items as &$item) {
                $inventory = $db->table('branch_products')->where('product_id', $item['product_id'])
                    ->where('branch_id', $sale['branch_id'])->get()->getRowArray();
                $item['restock_problem'] = $inventory
                    ? ((int) ($item['remaining_qty'] ?? 1) > 0 ? $batchService->lineRestockProblem($item, date('Y-m-d')) : null)
                    : 'The original branch inventory is unavailable.';
            }
            unset($item);
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(site_url('cashier/sales/history'))->with('error', $e->getMessage());
        }
        $originalProductIds = array_values(array_unique(array_filter(array_map('intval', array_column($items, 'product_id')))));
        $sellableQty = \App\Libraries\StockBatches::sellableQtySql($db, 'bp', date('Y-m-d'));
        $productsBuilder = $db->table('branch_products bp')->select('bp.*, p.product_name, p.sku, p.unit', false)->select($sellableQty . ' AS sellable_stock', false)
            ->join('products p', 'p.id = bp.product_id')->where('bp.branch_id', $sale['branch_id'])
            ->where('bp.status', 'active')->where('bp.deleted_at', null)->where('bp.is_deleted', 0)->where('bp.is_permanently_deleted', 0)->where('p.status', 'active')->where('p.deleted_at', null)->where('p.is_deleted', 0)->where('p.is_permanently_deleted', 0)->where($sellableQty . ' > 0', null, false);
        if ($originalProductIds) $productsBuilder->whereNotIn('bp.product_id', $originalProductIds);
        $products = $productsBuilder->orderBy('p.product_name', 'ASC')->get()->getResultArray();
        // The exchange offers only units that can still be sold (unexpired batches).
        foreach ($products as &$replacementProduct) $replacementProduct['stock'] = (int) $replacementProduct['sellable_stock'];
        unset($replacementProduct);
        $discounts = $db->table('discounts')->where('applies_to', 'all')->where('status', 'active')->where('deleted_at', null)->where('is_deleted', 0)->where('is_permanently_deleted', 0)
            ->groupStart()->where('start_date', null)->orWhere('start_date <=', date('Y-m-d'))->groupEnd()
            ->groupStart()->where('end_date', null)->orWhere('end_date >=', date('Y-m-d'))->groupEnd()
            ->orderBy('discount_name')->get()->getResultArray();
        if (!array_key_exists('discount_id', $input) && !empty($sale['discount_id'])) {
            foreach ($discounts as $discount) {
                if ((int) $discount['id'] === (int) $sale['discount_id']) {
                    $input['discount_id'] = (string) $discount['id'];
                    break;
                }
            }
        }
        $token = bin2hex(random_bytes(24));
        session()->set('exchange_' . $saleId, ['token' => $token, 'input' => $input]);
        $branchName = $db->table('branches')->where('id', $sale['branch_id'])->get()->getRowArray()['branch_name'] ?? 'Selling branch';
        return view('cashier/sales/exchange', compact('sale', 'items', 'products', 'discounts', 'token', 'input', 'error', 'branchName'));
    }

    private function draft(int $saleId): ?array
    {
        $draft = session('exchange_' . $saleId);
        $token = $this->request->getPost('exchange_token');
        return is_array($draft) && is_string($token) && hash_equals($draft['token'], $token) ? $draft : null;
    }

    public function review(int $saleId)
    {
        if (!$draft = $this->draft($saleId)) return redirect()->to(site_url('cashier/sales/exchange/' . $saleId))->with('error', 'This exchange form expired. Please review your selection again.');
        $input = $this->request->getPost();
        try {
            $quote = (new ProductExchange(db_connect()))->quote($saleId, $input, (int) session('branch_id'), (string) session('role'));
        } catch (\InvalidArgumentException $e) {
            return $this->renderForm($saleId, $input, $e->getMessage());
        }
        $draft['input'] = $input;
        $draft['revision'] = $quote['revision'];
        session()->set('exchange_' . $saleId, $draft);
        $branchName = db_connect()->table('branches')->where('id', $quote['sale']['branch_id'])->get()->getRowArray()['branch_name'] ?? 'Selling branch';
        return view('cashier/sales/exchange', ['sale' => $quote['sale'], 'quote' => $quote, 'token' => $draft['token'], 'branchName' => $branchName]);
    }

    public function complete(int $saleId)
    {
        $draft = $this->draft($saleId);
        if (!$draft || empty($draft['revision'])) return redirect()->to(site_url('cashier/sales/exchange/' . $saleId))->with('error', 'Review the exchange before confirming it.');
        try {
            $newId = (new ProductExchange(db_connect()))->complete($saleId, $draft['input'], (int) session('branch_id'), (string) session('role'), (int) session('user_id'), $draft['token'], $draft['revision']);
        } catch (\InvalidArgumentException $e) {
            return $this->renderForm($saleId, $draft['input'], $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', 'Exchange failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->to(site_url('cashier/sales/exchange/' . $saleId))->with('error', 'The exchange could not be saved. No exchange changes were committed. Please review and try again.');
        }
        return redirect()->to(site_url('cashier/sales/receipt/' . $newId));
    }
}
