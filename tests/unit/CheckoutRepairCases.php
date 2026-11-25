<?php

trait CheckoutRepairCases
{
    private function checkoutRepairPromo(int $id = 1, int $productId = 1, int $percent = 50): void
    {
        $this->db->table('discounts')->insert(['id' => $id, 'discount_name' => 'Automatic offer ' . $id,
            'discount_type' => 'percentage', 'discount_value' => $percent, 'applies_to' => 'product',
            'product_id' => $productId, 'minimum_purchase' => 0, 'status' => 'active']);
    }

    public function testCheckoutRepairBlankSelectionStillAppliesServerPromotionWithoutPreview(): void
    {
        $this->checkoutRepairPromo();
        $post = $this->checkoutPost(['discount_id' => '', 'amount_paid' => '10']);
        $this->controller(\App\Controllers\Cashier\SalesController::class, $post)->checkout();
        $sale = $this->db->table('sales')->where('id', 2)->get()->getRowArray();
        $this->assertNotNull($sale);
        $this->assertSame(5.0, (float)$sale['final_total']);
        $this->assertSame(5.0, (float)$sale['discount_amount']);
        $this->assertSame(5.0, (float)$sale['change_amount']);
        $item = $this->db->table('sale_items')->where('sale_id', 2)->get()->getRowArray();
        $this->assertSame(5.0, (float)$item['discount_applied']);
        $this->assertSame(9, (int)$this->row('branch_products')['stock']);
    }

    public function testCheckoutRepairBenefitCannotDisableFavorablePromoByRemovingOrChangingFlag(): void
    {
        $this->checkoutRepairPromo();
        foreach (['pwd', 'senior_citizen'] as $kind) foreach ([null, '0'] as $flag) {
            $post = $this->statutoryCheckoutPost($kind, ['amount_paid' => '112']);
            if ($flag !== null) $post['include_product_promos'] = $flag;
            $this->controller(\App\Controllers\Cashier\SalesController::class, $post)->checkout();
            $sale = $this->db->table('sales')->orderBy('id', 'DESC')->get()->getRowArray();
            $this->assertSame(56.0, (float)$sale['final_total']);
            $this->assertSame(56.0, (float)$sale['discount_amount']);
            $item = $this->db->table('sale_items')->where('sale_id', $sale['id'])->get()->getRowArray();
            $this->assertSame(0.0, (float)$item['vat_exemption_amount']);
            $this->assertNull($item['statutory_discount_kind']);
        }
    }

    public function testCheckoutRepairLegacyProductSelectorStillAppliesOtherMatchingPromos(): void
    {
        $this->seedExchangeReplacement(2, 30);
        $this->checkoutRepairPromo(1, 1, 20); $this->checkoutRepairPromo(2, 2, 50);
        $post = $this->checkoutPost(['items' => [1 => 1, 2 => 1], 'discount_id' => 1, 'amount_paid' => '40']);
        session()->set('cart', [1 => ['product_id' => 1, 'quantity' => 1], 2 => ['product_id' => 2, 'quantity' => 1]]);
        $this->controller(\App\Controllers\Cashier\SalesController::class, $post)->checkout();
        $sale = $this->db->table('sales')->where('id', 2)->get()->getRowArray();
        $this->assertNotNull($sale);
        $this->assertSame(23.0, (float)$sale['final_total']);
        $this->assertSame(17.0, (float)$sale['discount_amount']);
        $this->assertNull($sale['discount_id']);
    }

    public function testCheckoutRepairBlankSelectionRejectsStaleSubmittedPromotionPreview(): void
    {
        $this->checkoutRepairPromo();
        $post = $this->checkoutPost(['discount_id' => '', 'auto_promo_amount' => '0.00', 'auto_promo_snapshot' => '[]']);
        $this->controller(\App\Controllers\Cashier\SalesController::class, $post)->checkout();
        $this->assertSame(1, $this->db->table('sales')->countAllResults());
        $this->assertSame(0, $this->db->table('stock_logs')->countAllResults());
        $this->assertStringContainsString('promotions changed', (string)session()->getFlashdata('error'));
        $this->assertNotEmpty(session('cart'));
    }

    public function testCheckoutRepairNewBetterOfferDuringCheckoutRollsBackSaleAndStock(): void
    {
        $this->checkoutRepairPromo(1, 1, 10);
        $post = $this->checkoutPost(['discount_id' => '', 'auto_promo_amount' => '1.00', 'auto_promo_id' => '1']);
        $this->db->beforeInventoryLock = static function ($db) {
            $db->table('discounts')->insert(['id' => 2, 'discount_name' => 'New better offer', 'discount_type' => 'percentage',
                'discount_value' => 50, 'applies_to' => 'product', 'product_id' => 1, 'minimum_purchase' => 0, 'status' => 'active']);
        };
        $this->controller(\App\Controllers\Cashier\SalesController::class, $post)->checkout();
        $this->assertSame(1, $this->db->table('sales')->countAllResults());
        $this->assertSame(1, $this->db->table('sale_items')->countAllResults());
        $this->assertSame(10, (int)$this->row('branch_products')['stock']);
        $this->assertSame(0, $this->db->table('stock_logs')->countAllResults());
        $this->assertStringContainsString('promotions changed', (string)session()->getFlashdata('error'));
    }

    public function testCheckoutRepairDigitalPaymentsRequireExactTotalAndReference(): void
    {
        foreach (['gcash', 'card'] as $method) foreach ([['100', 'REF-100'], ['10', ''], ['9.99', 'REF-99'], ['10.001', 'REF-1'], ['1e1', 'REF-2']] as [$amount, $reference]) {
            $post = $this->checkoutPost(['payment_method' => $method, 'amount_paid' => $amount, 'reference_no' => $reference]);
            $this->controller(\App\Controllers\Cashier\SalesController::class, $post)->checkout();
            $this->assertSame(1, $this->db->table('sales')->countAllResults());
            $this->assertSame(10, (int)$this->row('branch_products')['stock']);
            $this->assertSame(0, $this->db->table('stock_logs')->countAllResults());
            $this->assertNotEmpty(session('cart'));
        }
    }

    public function testCheckoutRepairExactDigitalPaymentsSaveZeroChangeAndReference(): void
    {
        foreach (['gcash', 'card'] as $method) {
            $post = $this->checkoutPost(['payment_method' => $method, 'amount_paid' => '10.00', 'reference_no' => ($method === 'gcash' ? '0012345678901' : 'CONFIRMED-card')]);
            $response = $this->controller(\App\Controllers\Cashier\SalesController::class, $post)->checkout();
            $this->assertStringContainsString('/completed/', $response->getHeaderLine('Location'));
            $sale = $this->db->table('sales')->orderBy('id', 'DESC')->get()->getRowArray();
            $this->assertSame(10.0, (float)$sale['amount_paid']);
            $this->assertSame(0.0, (float)$sale['change_amount']);
            $this->assertSame(($method === 'gcash' ? '0012345678901' : 'CONFIRMED-card'), $sale['reference_no']);
        }
    }

    public function testCheckoutRepairRejectsStructuredPaymentValuesWithoutWritingSale(): void
    {
        foreach (['payment_method', 'amount_paid', 'reference_no', 'notes'] as $field) {
            $post = $this->checkoutPost(['payment_method' => 'gcash', 'amount_paid' => '10', 'reference_no' => 'PAY-10']);
            $post[$field] = ['not a payment field'];
            $this->controller(\App\Controllers\Cashier\SalesController::class, $post)->checkout();
            $this->assertSame(1, $this->db->table('sales')->countAllResults());
            $this->assertSame(10, (int)$this->row('branch_products')['stock']);
            $this->assertStringContainsString('payment details', (string)session()->getFlashdata('error'));
        }
    }

    public function testCheckoutRepairNoncashUsesDiscountedExactAmountAndCashStillReturnsChange(): void
    {
        $this->checkoutRepairPromo();
        $post = $this->checkoutPost(['discount_id' => '', 'payment_method' => 'gcash', 'amount_paid' => '5', 'reference_no' => '0012345678902']);
        $this->controller(\App\Controllers\Cashier\SalesController::class, $post)->checkout();
        $sale = $this->db->table('sales')->where('id', 2)->get()->getRowArray();
        $this->assertNotNull($sale); $this->assertSame(5.0, (float)$sale['final_total']);
        $this->assertSame(0.0, (float)$sale['change_amount']);
        $this->controller(\App\Controllers\Cashier\SalesController::class, $this->checkoutPost(['amount_paid' => '20']))->checkout();
        $cash = $this->db->table('sales')->where('id', 3)->get()->getRowArray();
        $this->assertSame(15.0, (float)$cash['change_amount']);
        $this->assertNull($cash['reference_no']);
    }
}
