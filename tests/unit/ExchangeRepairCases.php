<?php

trait ExchangeRepairCases
{
    private function exchangeRepairArtifacts(): string
    {
        $dir = WRITEPATH . 'repairs-20261028/exchange/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        return $dir;
    }

    private function exchangeRepairPromo(int $id, string $scope, float $value, array $replace = []): void
    {
        if ($scope === 'category' && !$this->db->tableExists('categories')) {
            $this->db->query('CREATE TABLE categories (id INTEGER PRIMARY KEY, category_name TEXT, deleted_at TEXT, is_deleted INTEGER DEFAULT 0, is_permanently_deleted INTEGER DEFAULT 0)');
            $this->db->table('categories')->insert(['id' => 7, 'category_name' => 'Exchange category']);
            $this->db->resetDataCache();
        }
        $this->db->table('discounts')->insert(array_replace(['id' => $id, 'discount_name' => 'Exchange promo ' . $id,
            'discount_type' => 'percentage', 'discount_value' => $value, 'applies_to' => $scope,
            'product_id' => $scope === 'product' ? 100 : null, 'category_id' => $scope === 'category' ? 7 : null,
            'minimum_purchase' => 0, 'status' => 'active'], $replace));
    }

    public function testExchangeRepairCannotResellUnknownOriginalExpiryOrChangedDelivery(): void
    {
        $input = $this->exchangeInput(); $service = new \App\Libraries\ProductExchange($this->db);
        $original = $this->row('sale_items');
        foreach ([['stock_delivery_id' => null], ['expiration_date_at_sale' => null], ['stock_delivery_id' => str_repeat('d', 32)]] as $bad) {
            $this->db->table('sale_items')->where('id', 1)->update(array_replace($original, $bad));
            try { $service->quote(1, $input, 1, 'admin'); $this->fail('Unproven return accepted'); }
            catch (InvalidArgumentException $error) { $this->assertMatchesRegularExpression('/delivery|expiry/i', $error->getMessage()); }
            $quarantine = $input; $quarantine['conditions'][1] = 'quarantined';
            $this->assertSame(0, $service->quote(1, $quarantine, 1, 'admin')['returns'][1]['restock']);
        }
        $this->assertSame(1, $this->db->table('sales')->countAllResults());
        $this->assertSame(0, $this->db->table('refund_items')->countAllResults());
    }

    public function testExchangeRepairQuoteIncludesCurrentReplacementDeliveryAndFutureExpiry(): void
    {
        $input = $this->exchangeInput(); $service = new \App\Libraries\ProductExchange($this->db);
        $q = $service->quote(1, $input, 1, 'admin');
        foreach ([['expiration_date' => '2099-07-01'], ['stock_delivery_id' => str_repeat('c', 32)]] as $change) {
            $original = $this->db->table('branch_products')->where('id', 100)->get()->getRowArray();
            $this->db->table('branch_products')->where('id', 100)->update($change);
            try { $service->complete(1, $input, 1, 'admin', 1, str_repeat('1', 48), $q['revision']); $this->fail('Stale delivery quote accepted'); }
            catch (InvalidArgumentException $error) { $this->assertStringContainsString('Review', $error->getMessage()); }
            $this->assertSame(1, $this->db->table('sales')->countAllResults());
            $this->assertSame(0, $this->db->table('refund_items')->countAllResults());
            $this->db->table('branch_products')->where('id', 100)->update($original);
        }
    }

    public function testExchangeRepairRechecksReturnedDeliveryAtConfirmation(): void
    {
        $input = $this->exchangeInput(); $service = new \App\Libraries\ProductExchange($this->db);
        $q = $service->quote(1, $input, 1, 'admin');
        $this->db->beforeInventoryLock = static fn($db) => $db->table('branch_products')->where('id', 1)->update(['stock_delivery_id' => str_repeat('e', 32)]);
        try { $service->complete(1, $input, 1, 'admin', 1, str_repeat('2', 48), $q['revision']); $this->fail('Changed return delivery was restocked'); }
        catch (InvalidArgumentException $error) { $this->assertStringContainsString('delivery', $error->getMessage()); }
        $this->assertSame(0, $this->db->table('refund_items')->countAllResults());
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
    }

    public function testExchangeRepairAppliesProductAndCategoryPromosWithoutClientFlagsAndSnapshotsReceipt(): void
    {
        $input = $this->exchangeInput(['replacements' => [100 => 3, 101 => 1], 'include_product_promos' => '0']);
        $this->seedExchangeReplacement(101, 20);
        $this->db->table('products')->whereIn('id', [100,101])->update(['category_id' => 7]);
        $this->exchangeRepairPromo(1, 'category', 10); $this->exchangeRepairPromo(2, 'product', 50);
        $service = new \App\Libraries\ProductExchange($this->db); $q = $service->quote(1, $input, 1, 'admin');
        $this->assertSame(1700, $q['discount']); $this->assertSame(3300, $q['total']); $this->assertSame(1300, $q['difference']);
        $this->assertSame([1500,200], array_column($q['replacements'], 'discount'));
        $this->assertCount(2, $q['promotions']);
        $id = $service->complete(1, $input, 1, 'admin', 1, str_repeat('3', 48), $q['revision']);
        $sale = $this->db->table('sales')->where('id', $id)->get()->getRowArray();
        $this->assertSame(17.0, (float) $sale['discount_amount']); $this->assertSame(33.0, (float) $sale['final_total']);
        $breakdown = \App\Libraries\ReceiptInvoice::discountBreakdown($sale);
        $this->assertSame(17.0, $breakdown['promotion']);
        $this->assertStringContainsString('Exchange promo 2', $breakdown['promotion_name']);
        $metadata = json_decode($sale['receipt_metadata'], true); $this->assertCount(2, $metadata['product_promotions']);
        $this->assertSame([15.0,2.0], array_map('floatval', array_column($metadata['product_promotions'], 'amount')));
        $this->assertSame($id, $service->complete(1, $input, 1, 'admin', 1, str_repeat('3', 48), $q['revision']));
        $this->assertSame(2, $this->db->table('sales')->countAllResults());
    }

    public function testExchangeRepairLegacyProductSelectorStillAppliesOtherAutomaticPromos(): void
    {
        $input = $this->exchangeInput(['discount_id' => '1', 'replacements' => [100 => 1, 101 => 1]]);
        $this->seedExchangeReplacement(101, 10);
        $this->exchangeRepairPromo(1, 'product', 20); $this->exchangeRepairPromo(2, 'product', 30, ['product_id' => 101]);
        $this->assertFalse($this->db->tableExists('categories'));
        $q = (new \App\Libraries\ProductExchange($this->db))->quote(1, $input, 1, 'admin');
        $this->assertSame(500, $q['discount']); $this->assertCount(2, $q['promotions']);
    }

    public function testExchangeRepairManualAllProductDiscountReplacesRatherThanStacksPromos(): void
    {
        $input = $this->exchangeInput(['discount_id' => '2']);
        $this->exchangeRepairPromo(1, 'product', 50); $this->exchangeRepairPromo(2, 'all', 10);
        $q = (new \App\Libraries\ProductExchange($this->db))->quote(1, $input, 1, 'admin');
        $this->assertSame(300, $q['discount']); $this->assertSame(2700, $q['total']);
        $this->assertSame([], $q['promotions']); $this->assertSame('2', $q['discountId']);
    }

    private function exchangeRepairCategoryLineage(string $type = 'fixed', ?float $cap = null): array
    {
        $input = $this->exchangeInput(); $this->db->table('products')->whereIn('id', [1,100])->update(['category_id' => 7]);
        $this->exchangeRepairPromo(1, 'category', $type === 'fixed' ? 10 : 20, ['discount_type' => $type, 'max_discount_amount' => $cap]);
        $metadata = \App\Libraries\ReceiptInvoice::snapshot(['discount_name' => 'Exchange promo 1'], null, null, null,
            [['id' => 1, 'product_id' => 1, 'name' => 'Exchange promo 1', 'scope' => 'category', 'category_id' => 7, 'amount' => 10]]);
        $this->db->table('sales')->where('id', 1)->update(['discount_id' => null, 'discount_amount' => 10, 'final_total' => 40, 'receipt_metadata' => $metadata]);
        $this->db->table('sale_items')->where('id', 1)->update(['discount_applied' => 10]);
        return $input;
    }

    public function testExchangeRepairAutomaticFixedCategoryAllowanceIncludesSiblingReplacements(): void
    {
        $input = $this->exchangeRepairCategoryLineage(); $service = new \App\Libraries\ProductExchange($this->db);
        $q = $service->quote(1, $input, 1, 'admin');
        $this->assertSame(1600, $q['credit']); $this->assertSame(400, $q['discount']);
        $this->assertSame(600, $q['promotionBasis']['contexts'][1]['discount_cents']);
        $service->complete(1, $input, 1, 'admin', 1, str_repeat('4', 48), $q['revision']);
        $this->seedExchangeReplacement(101, 10); $this->db->table('products')->where('id',101)->update(['category_id'=>7]);
        $input['returns'] = [1 => 1]; $input['replacements'] = [101 => 1];
        $q = $service->quote(1, $input, 1, 'admin');
        $this->assertSame(800, $q['promotionBasis']['contexts'][1]['discount_cents']);
        $this->assertSame(200, $q['discount']);
    }

    public function testExchangeRepairAutomaticCategoryMinimumAndCapCountOnlyItsRetainedLineage(): void
    {
        $input = $this->exchangeRepairCategoryLineage('percentage', 10);
        $this->db->table('discounts')->where('id', 1)->update(['minimum_purchase' => 50, 'discount_value' => 50]);
        $q = (new \App\Libraries\ProductExchange($this->db))->quote(1, $input, 1, 'admin');
        $this->assertSame(3000, $q['promotionBasis']['contexts'][1]['eligible_cents']);
        $this->assertSame(400, $q['discount']);
        $this->db->table('sales')->where('id', 1)->update(['discount_id' => null, 'receipt_metadata' => null]);
        $q = (new \App\Libraries\ProductExchange($this->db))->quote(1, $input, 1, 'admin');
        $this->assertSame(0, $q['discount']);
    }

    public function testExchangeRepairPromotionChangesInvalidateReviewedQuoteEvenIfAmountIsEqual(): void
    {
        $input = $this->exchangeInput(); $this->exchangeRepairPromo(1, 'product', 20);
        $service = new \App\Libraries\ProductExchange($this->db); $q = $service->quote(1, $input, 1, 'admin');
        $this->db->table('discounts')->where('id', 1)->update(['discount_name' => 'Changed offer name']);
        try { $service->complete(1, $input, 1, 'admin', 1, str_repeat('5', 48), $q['revision']); $this->fail('Stale offer accepted'); }
        catch (InvalidArgumentException $error) { $this->assertStringContainsString('Review', $error->getMessage()); }
        $this->assertSame(1, $this->db->table('sales')->countAllResults());
        $this->assertSame(0, $this->db->table('refund_items')->countAllResults());
    }

    public function testExchangeRepairPromotionChangedAtInventoryLockCannotCommit(): void
    {
        $input = $this->exchangeInput(); $this->exchangeRepairPromo(1, 'product', 20);
        $service = new \App\Libraries\ProductExchange($this->db); $q = $service->quote(1, $input, 1, 'admin');
        $this->db->beforeInventoryLock = static fn($db) => $db->table('discounts')->where('id',1)->update(['discount_value'=>50]);
        try { $service->complete(1, $input, 1, 'admin', 1, str_repeat('6',48), $q['revision']); $this->fail('Changed offer committed'); }
        catch (InvalidArgumentException $error) { $this->assertStringContainsString('Review', $error->getMessage()); }
        $this->assertSame(0, $this->db->table('refund_items')->countAllResults());
        $this->assertSame(10, (int) $this->db->table('branch_products')->where('id',100)->get()->getRow('stock'));
    }

    public function testExchangeRepairFormHidesAutomaticRulesAndExplainsUnresellableHistory(): void
    {
        helper(['url','form']); $this->exchangeInput();
        $this->exchangeRepairPromo(1, 'product', 20); $this->exchangeRepairPromo(2, 'all', 10);
        $this->db->table('sale_items')->where('id', 1)->update(['expiration_date_at_sale' => null]);
        \Config\Services::resetSingle('renderer');
        $html = $this->controller(\App\Controllers\Cashier\ExchangeController::class, [])->form(1);
        $doc = new DOMDocument(); @$doc->loadHTML($html); $xp = new DOMXPath($doc);
        $this->assertSame(0, $xp->query('//select[@name="discount_id"]/option[@value="1"]')->length);
        $this->assertSame(1, $xp->query('//select[@name="discount_id"]/option[@value="2"]')->length);
        $this->assertSame(1, $xp->query('//select[@name="conditions[1]"]/option[@value="resellable"][@disabled]')->length);
        $this->assertStringContainsString('Product and category promos apply automatically', $html);
        file_put_contents($this->exchangeRepairArtifacts() . 'form.html', $html);
    }

    public function testExchangeRepairQuoteDisplaysAutomaticOfferNamesAndExactShares(): void
    {
        helper(['url','form']); $this->exchangeInput(); $this->exchangeRepairPromo(1, 'product', 20);
        $this->controller(\App\Controllers\Cashier\ExchangeController::class, [])->form(1);
        $post = $this->exchangeInput(); $post['exchange_token'] = session('exchange_1')['token'];
        \Config\Services::resetSingle('renderer');
        $html = $this->controller(\App\Controllers\Cashier\ExchangeController::class, $post)->review(1);
        $this->assertStringContainsString('Exchange overview', $html);
        $this->assertStringContainsString('Exchange promo 1', $html);
        $this->assertStringContainsString('₱6.00', $html);
        file_put_contents($this->exchangeRepairArtifacts() . 'overview.html', $html);
    }

    public function testExchangeRepairRejectsAliasedQuantityKeys(): void
    {
        $input = $this->exchangeInput(['returns'=>[1=>1,'01'=>2]]);
        try {(new \App\Libraries\ProductExchange($this->db))->quote(1,$input,1,'admin');$this->fail('Aliased ID accepted');}
        catch(InvalidArgumentException $error){$this->assertStringContainsString('whole numbers',$error->getMessage());}
        $this->assertSame(0,$this->db->table('refund_items')->countAllResults());
    }

    public function testExchangeRepairLockedCompletionReadsRefundUsageAndMasterBalancesWithLocks(): void
    {
        $input = $this->exchangeInput(); $service = new \App\Libraries\ProductExchange($this->db);
        $q = $service->quote(1, $input, 1, 'admin');
        $service->complete(1, $input, 1, 'admin', 1, str_repeat('9', 48), $q['revision']);
        $locks = implode("\n", $this->db->locks);
        $this->assertStringContainsString('FROM refund_items WHERE sale_item_id = ? ORDER BY id FOR UPDATE', $locks);
        $this->assertStringContainsString('SELECT stock FROM branch_products WHERE product_id = ? ORDER BY id FOR UPDATE', $locks);
    }

    public function testExchangeRepairArchivedOrMissingCategoryCannotSuppressValidProductPromo(): void
    {
        $input = $this->exchangeInput(); $this->db->table('products')->where('id',100)->update(['category_id'=>7]);
        $this->exchangeRepairPromo(1,'category',50); $this->exchangeRepairPromo(2,'product',10);
        $service = new \App\Libraries\ProductExchange($this->db);
        $this->assertSame(1500,$service->quote(1,$input,1,'admin')['discount']);
        foreach ([['deleted_at'=>'2026-10-28 09:00:00'],['is_deleted'=>1],['is_permanently_deleted'=>1]] as $archived) {
            $this->db->table('categories')->where('id',7)->update(array_replace(['deleted_at'=>null,'is_deleted'=>0,'is_permanently_deleted'=>0],$archived));
            $q = $service->quote(1,$input,1,'admin');
            $this->assertSame(300,$q['discount']); $this->assertCount(1,$q['promotions']);
            $this->assertSame(2,(int)$q['promotions'][0]['discount']['id']);
        }
        $this->db->table('categories')->where('id',7)->delete();
        $this->assertSame(300,$service->quote(1,$input,1,'admin')['discount']);
    }

    public function testExchangeRepairCategoryArchivedAtConfirmationInvalidatesReviewedOffer(): void
    {
        $input = $this->exchangeInput(); $this->db->table('products')->where('id',100)->update(['category_id'=>7]);
        $this->exchangeRepairPromo(1,'category',50); $this->exchangeRepairPromo(2,'product',10);
        $service = new \App\Libraries\ProductExchange($this->db); $q = $service->quote(1,$input,1,'admin');
        $this->db->beforeInventoryLock = static fn($db) => $db->table('categories')->where('id',7)->update(['is_deleted'=>1]);
        try {$service->complete(1,$input,1,'admin',1,str_repeat('a',48),$q['revision']);$this->fail('Archived category quote committed');}
        catch(InvalidArgumentException $error){$this->assertStringContainsString('Review',$error->getMessage());}
        $this->assertSame(1,$this->db->table('sales')->countAllResults());
        $this->assertSame(0,$this->db->table('refund_items')->countAllResults());
        $this->assertStringContainsString('SELECT * FROM categories WHERE id IN (?) ORDER BY id FOR UPDATE',implode("\n",$this->db->locks));
    }
}
