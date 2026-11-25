<?php

trait TransactionOverviewCases
{
    private function transactionOverviewRender(string $class, string $action, array $post = []): string
    {
        \Config\Services::resetSingle('renderer');
        $controller = $this->controller($class, $post); service('request')->setGlobal('get', []);
        return $controller->$action(1);
    }

    private function transactionOverviewJson(string $html, string $id): array
    {
        $doc = new DOMDocument(); @$doc->loadHTML($html);
        $node = (new DOMXPath($doc))->query('//script[@id="' . $id . '"]')->item(0);
        $this->assertNotNull($node);
        return json_decode($node->textContent, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testTransactionOverviewRefundUsesRecordedRemainingNetAndKeepsTokensUntilConfirmation(): void
    {
        $this->db->table('sale_items')->where('id', 1)->update(['quantity' => 3, 'price' => 0.3333, 'subtotal' => 1, 'discount_applied' => 0, 'vat_exemption_amount' => 0]);
        $this->db->table('sales')->where('id', 1)->update(['total_amount' => 1, 'final_total' => 1, 'amount_paid' => 1, 'change_amount' => 0]);
        $this->db->table('refund_items')->insert(['sale_id' => 1, 'sale_item_id' => 1, 'product_id' => 1, 'quantity_refunded' => 1, 'refund_subtotal' => 0.33, 'return_condition' => 'damaged', 'created_at' => '2026-10-23 09:00:00']);
        $html = $this->transactionOverviewRender(\App\Controllers\Cashier\SalesController::class, 'refundForm');
        $this->assertStringContainsString('data-remaining-cents="67"', $html);
        $this->assertStringContainsString('data-transaction-overview="refund"', $html);
        $this->assertStringContainsString('Refund overview', $html);
        $this->assertStringContainsString('Confirm &amp; process refund', $html);
        $this->assertSame('TEST-1', $this->transactionOverviewJson($html, 'refundForm-overview-data')['invoice']);
        $token = session('refund_token_1');
        $this->assertSame(48, strlen($token));
        $this->assertSame(1, $this->db->table('refund_items')->countAllResults());
        $post = ['_refund_token' => $token, 'refund_method' => 'cash', 'reason' => 'One damaged unit', 'refund' => [1 => 1], 'return_condition' => [1 => 'damaged']];
        $this->controller(\App\Controllers\Cashier\SalesController::class, $post)->refundPartial(1);
        $last = $this->db->table('refund_items')->orderBy('id', 'DESC')->get(1)->getRowArray();
        $this->assertSame(0.34, (float) $last['refund_subtotal']);
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
    }

    public function testTransactionOverviewRefundRoundingExamplesUseActualSavedPayouts(): void
    {
        $examples = [];
        foreach ([[100,3,1], [67,2,1], [1,2,1], [10001,7,3], [100,3,3], [5,8,4], [16003,9,5]] as [$net, $quantity, $returned]) {
            $this->db->table('refund_items')->emptyTable();
            $this->db->table('sale_items')->where('id', 1)->update(['quantity' => $quantity, 'price' => $net / 100 / $quantity, 'subtotal' => $net / 100, 'discount_applied' => 0, 'vat_exemption_amount' => 0]);
            $this->db->table('sales')->where('id', 1)->update(['status' => 'completed', 'total_amount' => $net / 100, 'discount_amount' => 0, 'final_total' => $net / 100, 'amount_paid' => $net / 100, 'change_amount' => 0]);
            session()->set('refund_token_1', 'rounding-example');
            $this->controller(\App\Controllers\Cashier\SalesController::class, ['_refund_token' => 'rounding-example', 'refund_method' => 'cash', 'reason' => 'Damaged returned units', 'refund' => [1 => $returned], 'return_condition' => [1 => 'damaged']])->refundPartial(1);
            $row = $this->db->table('refund_items')->get()->getRowArray();
            $this->assertNotNull($row, (string) session()->getFlashdata('error'));
            $examples[] = ['quantity' => $returned, 'remainingQuantity' => $quantity, 'remainingCents' => $net, 'savedCents' => \App\Libraries\DiscountPolicy::cents((float) $row['refund_subtotal'])];
        }
        if (getenv('PHX_TRANSACTION_OVERVIEW_FIXTURES') === '1') {
            $dir = WRITEPATH . 'transaction-overview-review'; if (!is_dir($dir)) mkdir($dir, 0777, true);
            file_put_contents($dir . '/refund-expectations.json', json_encode($examples, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }
    }

    public function testTransactionOverviewCorrectionBaselineIsSavedAndSafelyEncodedOnRejectedEdit(): void
    {
        $saved = 'Saved </script><script>window.overviewInjected=1</script>';
        $this->db->table('sales')->where('id', 1)->update(['notes' => $saved]);
        $html = $this->transactionOverviewRender(\App\Controllers\Admin\SaleCorrection::class, 'update', $this->correctionPost(['correction_reason' => '', 'notes' => 'Proposed notes']));
        $baseline = $this->transactionOverviewJson($html, 'correctionForm-overview-data');
        $this->assertSame($saved, $baseline['notes']);
        $this->assertSame(50, (int) $baseline['final_total']);
        $this->assertStringNotContainsString('<script>window.overviewInjected', $html);
        $this->assertStringContainsString('Proposed notes', $html);
        $this->assertStringContainsString('data-transaction-overview="correction"', $html);
        $this->assertStringContainsString('Confirm &amp; apply correction', $html);
        $this->assertSame($saved, $this->row('sales')['notes']);
    }

    public function testTransactionOverviewExchangeReviewIsReadOnlyAndRetainsSelections(): void
    {
        $this->seedExchangeReplacement(100, 6);
        $this->transactionOverviewRender(\App\Controllers\Cashier\ExchangeController::class, 'form');
        $draft = session('exchange_1');
        $input = $this->exchangeInput(['returns' => [1 => 1], 'conditions' => [1 => 'damaged'], 'replacements' => [100 => 2], 'reason' => 'Damaged item replacement', 'exchange_token' => $draft['token']]);
        $html = $this->transactionOverviewRender(\App\Controllers\Cashier\ExchangeController::class, 'review', $input);
        $this->assertStringContainsString('Exchange overview', $html);
        $this->assertStringContainsString('Branch A', $html);
        $this->assertStringContainsString('Collect from customer', $html);
        $this->assertStringContainsString('₱2.00', $html);
        $this->assertStringContainsString('Confirm &amp; process exchange', $html);
        $this->assertStringContainsString('Stock effect: remove 2', $html);
        $this->assertSame($input, session('exchange_1')['input']);
        $this->assertNotEmpty(session('exchange_1')['revision']);
        $this->assertSame(1, $this->db->table('sales')->countAllResults());
        $this->assertSame(0, $this->db->table('refund_items')->countAllResults());
        $this->assertSame(0, $this->db->table('stock_logs')->countAllResults());
    }

    public function testTransactionOverviewBrowserFixtures(): void
    {
        $refund = $this->transactionOverviewRender(\App\Controllers\Cashier\SalesController::class, 'refundForm');
        $correction = $this->transactionOverviewRender(\App\Controllers\Admin\SaleCorrection::class, 'edit');
        $this->seedExchangeReplacement(100, 6);
        $this->transactionOverviewRender(\App\Controllers\Cashier\ExchangeController::class, 'form');
        $input = $this->exchangeInput(['returns' => [1 => 1], 'conditions' => [1 => 'damaged'], 'replacements' => [100 => 2], 'exchange_token' => session('exchange_1')['token']]);
        $exchange = $this->transactionOverviewRender(\App\Controllers\Cashier\ExchangeController::class, 'review', $input);
        $this->db->table('discounts')->insert(['id' => 1, 'discount_name' => 'Recorded 20%', 'discount_type' => 'percentage', 'discount_value' => 20, 'applies_to' => 'all', 'minimum_purchase' => 0, 'status' => 'active']);
        $this->db->table('sales')->where('id', 1)->update(['discount_id' => 1, 'discount_amount' => 10, 'final_total' => 40, 'amount_paid' => 100, 'change_amount' => 60]);
        $this->db->table('sale_items')->where('id', 1)->update(['discount_applied' => 10]);
        $discountedCorrection = $this->transactionOverviewRender(\App\Controllers\Admin\SaleCorrection::class, 'edit');
        $pricing = new \App\Libraries\SaleCorrectionPricing($this->db); $sale = $this->row('sales'); $items = [$this->row('sale_items')]; $context = $pricing->context($sale, $items);
        $expected = $pricing->calculate($sale, $items, [1 => 4], $context);
        $this->db->table('sales')->where('id', 1)->update(['discount_id' => null, 'discount_amount' => 0, 'total_amount' => 1, 'final_total' => 1, 'amount_paid' => 1, 'change_amount' => 0]);
        $this->db->table('sale_items')->where('id', 1)->update(['quantity' => 3, 'price' => 0.3333, 'subtotal' => 1, 'discount_applied' => 0]);
        $roundedRefund = $this->transactionOverviewRender(\App\Controllers\Cashier\SalesController::class, 'refundForm');
        $this->assertStringContainsString('data-remaining-cents="100"', $roundedRefund);
        if (getenv('PHX_TRANSACTION_OVERVIEW_FIXTURES') === '1') {
            $dir = WRITEPATH . 'transaction-overview-review'; if (!is_dir($dir)) mkdir($dir, 0777, true);
            foreach (compact('refund', 'correction', 'exchange', 'discountedCorrection', 'roundedRefund') as $name => $html) file_put_contents($dir . '/' . $name . '.html', $html);
            file_put_contents($dir . '/correction-expectations.json', json_encode(['final' => $expected['final'], 'discount' => $expected['discount'], 'difference' => $expected['difference']], JSON_THROW_ON_ERROR));
        }
    }
}
