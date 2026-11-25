<?php
/** Selected 28 October inventory repairs; only isolated fixture database writes. */
trait InventoryRepairCases
{
    private function repairCompetingRefund(int $quantity, float $amount, ?float $invoiceTotal = null): void
    {
        $this->db->beforeSaleLock = static function ($db) use ($quantity, $amount, $invoiceTotal): void {
            $db->table('refund_items')->insert(['sale_id' => 1, 'sale_item_id' => 1, 'product_id' => 1,
                'quantity_refunded' => $quantity, 'refund_subtotal' => $amount, 'refunded_by' => 1,
                'return_condition' => 'quarantined', 'refund_method' => 'cash', 'reason' => 'Competing prior refund']);
            if ($invoiceTotal !== null) $db->table('sales')->where('id', 1)->update(['final_total' => $invoiceTotal]);
        };
    }

    public function testInventoryRepairLockedRefundRowsRejectACompetingQuantity(): void
    {
        $this->repairCompetingRefund(4, 40);
        $this->controller(\App\Controllers\Cashier\SalesController::class,
            $this->repairRefundPost(['refund' => [1 => 2]]))->refundPartial(1);
        $this->assertStringContainsString('refundable quantity changed', (string) session()->getFlashdata('error'));
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
        $this->assertSame(0, $this->db->table('stock_logs')->countAllResults());
        // SQLite's hook is rolled back with this fixture transaction; the test
        // checks the real locking query, not MySQL's scheduling implementation.
        $this->assertCount(1, array_filter($this->db->locks, static fn ($sql) => str_contains($sql, 'FROM refund_items WHERE sale_id = ?')));
    }

    public function testInventoryRepairLockedRefundRowsCapTheCurrentInvoiceRemainder(): void
    {
        $this->repairCompetingRefund(1, 35, 40);
        $this->controller(\App\Controllers\Cashier\SalesController::class,
            $this->repairRefundPost(['refund' => [1 => 4]]))->refundPartial(1);
        $this->assertStringContainsString('invoice amount', (string) session()->getFlashdata('error'));
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
        $this->assertSame(0, $this->db->table('stock_logs')->countAllResults());
    }

    public function testInventoryRepairLockedRefundBalancesDetermineFinalPayoutAndStatus(): void
    {
        $this->repairCompetingRefund(4, 40);
        $this->controller(\App\Controllers\Cashier\SalesController::class, $this->repairRefundPost())->refundPartial(1);
        $refunds = $this->db->table('refund_items')->get()->getResultArray();
        $this->assertCount(2, $refunds);
        $this->assertSame(5, (int) array_sum(array_column($refunds, 'quantity_refunded')));
        $this->assertSame(50.0, (float) array_sum(array_column($refunds, 'refund_subtotal')));
        $this->assertSame('refunded', $this->row('sales')['status']);
        $this->assertSame(11, (int) $this->row('branch_products')['stock']);
        $this->assertCount(1, array_filter($this->db->locks, static fn ($sql) => str_contains($sql, 'FROM refund_items WHERE sale_id = ?')));
        $this->assertCount(1, array_filter($this->db->locks, static fn ($sql) => str_contains($sql, 'SELECT id, quantity FROM sale_items')));
    }

    private function repairRefundPost(array $replace = []): array
    {
        session()->set('refund_token_1', 'inventory-repair');
        return array_replace(['_refund_token' => 'inventory-repair', 'refund' => [1 => 1], 'reason' => 'Inspect returned units',
            'refund_method' => 'cash', 'return_condition' => [1 => 'resellable']], $replace);
    }

    public function testInventoryRepairRejectsAliasedRefundIdsBeforeAnyMutation(): void
    {
        foreach (['01', '1x', '+1', '1.0', ' 1', '92233720368547758080'] as $alias) {
            $post = $this->repairRefundPost(['refund' => [1 => 5, $alias => 5]]);
            $this->controller(\App\Controllers\Cashier\SalesController::class, $post)->refundPartial(1);
            $this->assertSame(0, $this->db->table('refund_items')->countAllResults());
            $this->assertSame(10, (int) $this->row('branch_products')['stock']);
            $this->assertSame(0, $this->db->table('stock_logs')->countAllResults());
            $this->assertStringContainsString('identifiers', (string) session()->getFlashdata('error'));
        }
    }

    public function testInventoryRepairCapsRefundAtLockedInvoiceNet(): void
    {
        $this->db->beforeSaleLock = static function ($db): void { $db->table('sales')->where('id', 1)->update(['final_total' => 40]); };
        $this->controller(\App\Controllers\Cashier\SalesController::class,
            $this->repairRefundPost(['refund' => [1 => 5]]))->refundPartial(1);
        $this->assertSame(0, $this->db->table('refund_items')->countAllResults());
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
        $this->assertStringContainsString('invoice amount', (string) session()->getFlashdata('error'));
    }

    public function testInventoryRepairAcceptsCanonicalRefundAndRetryCannotDuplicateIt(): void
    {
        $post = $this->repairRefundPost(['refund' => [1 => 2]]);
        $this->controller(\App\Controllers\Cashier\SalesController::class, $post)->refundPartial(1);
        $this->controller(\App\Controllers\Cashier\SalesController::class, $post)->refundPartial(1);
        $this->assertSame(1, $this->db->table('refund_items')->countAllResults());
        $this->assertSame(20.0, (float) $this->row('refund_items')['refund_subtotal']);
        $this->assertSame(12, (int) $this->row('branch_products')['stock']);
        $this->assertSame(1, $this->db->table('stock_logs')->countAllResults());
    }

    public function testInventoryRepairUnknownSourceStillAllowsNonresellableReturns(): void
    {
        $this->db->table('sale_items')->where('id', 1)->update(['stock_delivery_id' => null, 'expiration_date_at_sale' => null]);
        foreach (['quarantined', 'damaged', 'expired'] as $condition) {
            $this->controller(\App\Controllers\Cashier\SalesController::class,
                $this->repairRefundPost(['return_condition' => [1 => $condition]]))->refundPartial(1);
        }
        $this->assertSame(3, $this->db->table('refund_items')->countAllResults());
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
        $this->assertSame(0, $this->db->table('stock_logs')->countAllResults());
        $html = $this->controller(\App\Controllers\Cashier\SalesController::class, [])->refundForm(1);
        $this->assertStringContainsString('value="resellable" disabled', $html);
        $this->assertStringContainsString('expiry at sale was not recorded', $html);
    }

    public function testInventoryRepairChecksReturnedDeliveryAgainUnderInventoryLock(): void
    {
        $this->db->beforeInventoryLock = static function ($db): void {
            $db->table('branch_products')->where('id', 1)->update(['stock_delivery_id' => str_repeat('b', 32)]);
        };
        $this->controller(\App\Controllers\Cashier\SalesController::class, $this->repairRefundPost())->refundPartial(1);
        $this->assertSame(0, $this->db->table('refund_items')->countAllResults());
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
        $this->assertStringContainsString('delivery', (string) session()->getFlashdata('error'));
    }

    public function testInventoryRepairRejectsExpiredAndOtherDeliveryQuantityCorrectionsBothWays(): void
    {
        foreach ([['stock_delivery_id' => str_repeat('b', 32)], ['expiration_date_at_sale' => '2000-01-01'], ['stock_delivery_id' => null]] as $snapshot) {
            $original = $this->row('sale_items');
            $this->db->table('sale_items')->where('id', 1)->update($snapshot);
            foreach ([3, 6] as $qty) {
                $html = $this->controller(\App\Controllers\Admin\SaleCorrection::class, $this->correctionPost(['items' => [1 => $qty]]))->update(1);
                $this->assertIsString($html);
                $this->assertSame(5, (int) $this->row('sale_items')['quantity']);
                $this->assertSame(10, (int) $this->row('branch_products')['stock']);
            }
            $this->db->table('sale_items')->where('id', 1)->update($original);
        }
        $this->assertSame(0, $this->db->table('stock_logs')->countAllResults());
    }

    public function testInventoryRepairReplacementRotatesSameExpiryAndBlocksOldQuarantineRelease(): void
    {
        $quarantine = $this->quarantineFixture();
        $this->db->table('branch_products')->where('id', 1)->update(['expiration_date' => '2030-01-01']);
        $this->db->table('sale_items')->where('id', 1)->update(['expiration_date_at_sale' => '2030-01-01']);
        $service = new \App\Libraries\ExpiryStockResolution($this->db);
        $row = $service->inventory(1, 'admin', 1);
        $input = ['outcome' => 'supplier_return', 'new_quantity' => 20, 'new_expiry' => '2030-01-01', 'reason' => 'Different supplier delivery', 'confirmed' => '1'];
        $this->assertTrue($service->resolve(1, 'replace', $input, 'admin', 1, 1, str_repeat('a', 48), $service::revision($row)));
        $delivery = $this->row('branch_products')['stock_delivery_id'];
        $this->assertNotSame($row['stock_delivery_id'], $delivery);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/D', $delivery);
        $this->assertFalse($service->resolve(1, 'replace', $input, 'admin', 1, 1, str_repeat('a', 48), $service::revision($row)));
        $this->assertSame($delivery, $this->row('branch_products')['stock_delivery_id']);
        $record = $quarantine->item(1, 1);
        $this->assertStringContainsString('delivery', $quarantine::releaseProblem($record));
        $this->assertSame(20, (int) $this->row('branch_products')['stock']);
        $this->assertSame(str_repeat('a', 32), $this->row('sale_items')['stock_delivery_id']);
        $this->assertSame(2, $this->db->table('stock_logs')->countAllResults());
    }

    public function testInventoryRepairReplacementRevisionIncludesDeliveryIdentity(): void
    {
        $service = new \App\Libraries\ExpiryStockResolution($this->db);
        $row = $service->inventory(1, 'admin', 1);
        $this->db->table('branch_products')->where('id', 1)->update(['stock_delivery_id' => str_repeat('b', 32)]);
        $this->assertNotSame($service::revision($row), $service::revision($service->inventory(1, 'admin', 1)));
    }

    public function testInventoryRepairLegacyReceivingRejectedAndModernAddIsOneTime(): void
    {
        $legacy = ['product_id' => 1, 'branch_id' => 1, 'stock' => 5, 'old_stock' => 10, 'stock_mode' => 'receive', 'remarks' => 'Delivery retry'];
        for ($n = 0; $n < 2; $n++) $this->controller(\App\Controllers\Products::class, $legacy)->updateStock();
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
        $this->assertSame(0, $this->db->table('stock_logs')->countAllResults());
        $post = $this->cashierStockPost('add', 5, 10);
        $this->controller(\App\Controllers\Products::class, $post)->updateStock();
        $delivery = $this->row('branch_products')['stock_delivery_id'];
        $this->controller(\App\Controllers\Products::class, $post)->updateStock();
        $this->assertSame(15, (int) $this->row('branch_products')['stock']);
        $this->assertNotSame(str_repeat('a', 32), $delivery);
        $this->assertSame($delivery, $this->row('branch_products')['stock_delivery_id']);
        $this->assertSame(1, $this->db->table('stock_logs')->countAllResults());
        $this->controller(\App\Controllers\Products::class, $this->cashierStockPost('remove', 2, 15))->updateStock();
        $this->assertSame($delivery, $this->row('branch_products')['stock_delivery_id']);
    }

    public function testInventoryRepairAbsoluteCountRotatesOnlyForIncreaseAndRejectsStaleReplay(): void
    {
        $post = ['product_id' => 1, 'branch_id' => 1, 'stock' => 15, 'old_stock' => 10, 'stock_mode' => 'count', 'remarks' => 'Physical stock count'];
        $this->controller(\App\Controllers\Products::class, $post)->updateStock();
        $delivery = $this->row('branch_products')['stock_delivery_id'];
        $this->assertNotSame(str_repeat('a', 32), $delivery);
        $this->controller(\App\Controllers\Products::class, $post)->updateStock();
        $this->assertSame(15, (int) $this->row('branch_products')['stock']);
        $this->assertSame(1, $this->db->table('stock_logs')->countAllResults());
        $post['stock'] = 12; $post['old_stock'] = 15;
        $this->controller(\App\Controllers\Products::class, $post)->updateStock();
        $this->assertSame($delivery, $this->row('branch_products')['stock_delivery_id']);
    }

    public function testInventoryRepairDeliveryRotationRollsBackWithFailedStockAudit(): void
    {
        $before = $this->row('branch_products');
        $this->db->query("CREATE TRIGGER fail_delivery_audit BEFORE INSERT ON activity_logs BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
        $this->controller(\App\Controllers\Products::class, $this->cashierStockPost('add', 2))->updateStock();
        $this->assertSame($before, $this->row('branch_products'));
        $this->assertSame(0, $this->db->table('stock_logs')->countAllResults());
    }

    public function testInventoryRepairNewProductHasItsOwnDeliveryIdentity(): void
    {
        $post = $this->expiryProductEditPost(['sku' => 'NEW-DELIVERY', 'product_name' => 'New stock product', 'expiration_date' => '2030-01-01']);
        $this->db->query('ALTER TABLE branch_products ADD COLUMN created_at TEXT');
        $this->controller(\App\Controllers\Products::class, $post)->store();
        $product = $this->db->table('products')->where('sku', 'NEW-DELIVERY')->get()->getRowArray();
        $this->assertNotNull($product);
        $inventory = $this->db->table('branch_products')->where('product_id', $product['id'])->get()->getRowArray();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/D', $inventory['stock_delivery_id']);
        $this->assertNotSame(str_repeat('a', 32), $inventory['stock_delivery_id']);
    }

    public function testInventoryRepairExistingProductBranchAssignmentGetsNewDeliveryIdentity(): void
    {
        $post = $this->expiryProductEditPost(['branch_id' => 2, 'expiration_date' => '2030-01-01']);
        $this->db->query('ALTER TABLE branch_products ADD COLUMN created_at TEXT');
        $this->db->table('branches')->insert(['id' => 2, 'branch_name' => 'New Branch', 'status' => 'active']);
        $this->controller(\App\Controllers\Products::class, $post)->store();
        $inventory = $this->db->table('branch_products')->where('branch_id', 2)->get()->getRowArray();
        $this->assertNotNull($inventory);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/D', $inventory['stock_delivery_id']);
        $this->assertNotSame(str_repeat('a', 32), $inventory['stock_delivery_id']);
    }

    public function testInventoryRepairCashierCatalogAssignmentGetsNewDeliveryIdentity(): void
    {
        $this->prepareCatalogBranch();
        $this->controller(\App\Controllers\Products::class, $this->catalogInput())->addExisting();
        $inventory = $this->db->table('branch_products')->where('branch_id', 2)->get()->getRowArray();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/D', $inventory['stock_delivery_id']);
        $this->assertNotSame(str_repeat('a', 32), $inventory['stock_delivery_id']);
    }

    public function testInventoryRepairProductCountIncreaseRotatesButReductionPreservesDelivery(): void
    {
        $post = $this->expiryProductEditPost(['stock' => 12, 'stock_reason' => 'Received new units']);
        $this->controller(\App\Controllers\Products::class, $post)->update(1);
        $delivery = $this->row('branch_products')['stock_delivery_id'];
        $this->assertNotSame(str_repeat('a', 32), $delivery);
        $post = array_replace($post, ['stock' => 11, 'old_stock' => 12, 'stock_reason' => 'Physical recount']);
        $this->controller(\App\Controllers\Products::class, $post)->update(1);
        $this->assertSame($delivery, $this->row('branch_products')['stock_delivery_id']);
        $this->assertSame(11, (int) $this->row('branch_products')['stock']);
    }
}
