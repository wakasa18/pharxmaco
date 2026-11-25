<?php

/** Configured expiry-year bounds without administrator overrides or live database writes. */
trait ExpiryYearRangeCases
{
    private function expiryYearRejected(callable $action): void
    {
        try { $action(); $this->fail('Expected expiry year-range rejection.'); }
        catch (InvalidArgumentException $error) { $this->assertNotSame('', $error->getMessage()); }
    }

    private function expiryYearSnapshot(): array
    {
        $snapshot = [];
        foreach (['products', 'branch_products', 'stock_logs', 'activity_logs', 'sale_items'] as $table) {
            $snapshot[$table] = $this->db->table($table)->orderBy('id')->get()->getResultArray();
        }
        return $snapshot;
    }

    /** Legacy fields from an already-open form must no longer grant an exception. */
    private function expiryYearOldOverride(): array
    {
        return ['expiry_range_reviewed' => '1', 'expiry_range_reason' => 'Verified sealed package', 'role' => 'admin'];
    }

    private function expiryYearMaximum(): string
    {
        return \App\Libraries\ExpiryDatePolicy::limits()['maximum'];
    }

    public function testExpiryYearRangeExactCalendarYearBoundsAndLeapDay(): void
    {
        $limits = \App\Libraries\ExpiryDatePolicy::limits('2026-10-28');
        $this->assertSame(['today' => '2026-10-28', 'minimum' => '2026-10-29', 'maximum' => '2036-12-31', 'yearsAhead' => 10], $limits);
        $this->assertSame('2027-01-01', \App\Libraries\ExpiryDatePolicy::limits('2026-12-31')['minimum']);
        $this->assertSame('2036-12-31', \App\Libraries\ExpiryDatePolicy::limits('2026-12-31')['maximum']);
        $this->assertSame('2028-02-29', \App\Libraries\ExpiryDatePolicy::limits('2028-02-28')['minimum']);
        $this->assertNull(\App\Libraries\ExpiryDatePolicy::assertAllowed('2028-02-29', null, '2026-10-28'));
        $this->assertNull(\App\Libraries\ExpiryDatePolicy::assertAllowed('2036-12-31', null, '2026-10-28'));
        $this->expiryYearRejected(fn () => \App\Libraries\ExpiryDatePolicy::assertAllowed('2037-01-01', null, '2026-10-28'));
    }

    public function testExpiryYearRangeUsesConfiguredHorizonAndInvalidSettingsFallBack(): void
    {
        $config = config(\Config\InventoryExpiry::class); $old = $config->yearsAhead;
        try {
            $config->yearsAhead = 5;
            $this->assertSame('2031-12-31', \App\Libraries\ExpiryDatePolicy::limits('2026-10-28')['maximum']);
            $this->assertNull(\App\Libraries\ExpiryDatePolicy::assertAllowed('2031-12-31', null, '2026-10-28'));
            $this->expiryYearRejected(fn () => \App\Libraries\ExpiryDatePolicy::assertAllowed('2032-01-01', null, '2026-10-28'));
            foreach ([0, -1, 101] as $invalid) {
                $config->yearsAhead = $invalid;
                $this->assertSame('2036-12-31', \App\Libraries\ExpiryDatePolicy::limits('2026-10-28')['maximum']);
            }
            $config->yearsAhead = 100;
            $this->assertSame('2126-12-31', \App\Libraries\ExpiryDatePolicy::limits('2026-10-28')['maximum']);
        } finally { $config->yearsAhead = $old; }
    }

    public function testExpiryYearRangeRejectsInvalidAndNonfutureChangedDates(): void
    {
        foreach (['0000-01-01', '2027-02-29', '2036-02-30', '2037-04-31', '2037-13-01', '2037-01-00', '2037-1-01', '10000-01-01', 'not-a-date',
            '2026-10-27', '2026-10-28'] as $date) {
            $this->expiryYearRejected(fn () => \App\Libraries\ExpiryDatePolicy::assertAllowed($date, null, '2026-10-28'));
        }
        $this->assertNull(\App\Libraries\ExpiryDatePolicy::assertAllowed(null, null, '2026-10-28'));
        $this->assertNull(\App\Libraries\ExpiryDatePolicy::assertAllowed('', null, '2026-10-28'));
    }

    public function testExpiryYearRangeKeepsUnchangedHistoryButLimitsChangedDates(): void
    {
        foreach (['2000-01-01', '2026-10-28', '2099-01-01'] as $original) {
            $this->assertNull(\App\Libraries\ExpiryDatePolicy::assertAllowed($original, $original, '2026-10-28'));
        }
        $this->expiryYearRejected(fn () => \App\Libraries\ExpiryDatePolicy::assertAllowed('2099-01-02', '2099-01-01', '2026-10-28'));
        $this->expiryYearRejected(fn () => \App\Libraries\ExpiryDatePolicy::assertAllowed('2026-10-27', '2000-01-01', '2026-10-28'));
    }

    public function testExpiryYearRangeUsesPhilippineDateRegardlessOfDefaultTimezone(): void
    {
        $old = date_default_timezone_get();
        try {
            date_default_timezone_set('America/Los_Angeles');
            $manila = new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'));
            $this->assertSame($manila->format('Y-m-d'), \App\Libraries\ExpiryDatePolicy::limits()['today']);
            $this->assertSame($manila->modify('+1 day')->format('Y-m-d'), \App\Libraries\ExpiryDatePolicy::limits()['minimum']);
        } finally { date_default_timezone_set($old); }
    }

    public function testExpiryYearRangeNewProductRejectsBothRolesAndOldOverrideWithoutWrites(): void
    {
        $post = $this->expiryProductEditPost(['sku' => 'YEAR-RANGE-NEW', 'expiration_date' => '2099-01-01']);
        $this->db->query('ALTER TABLE branch_products ADD COLUMN created_at TEXT');
        $before = $this->expiryYearSnapshot();
        foreach (['admin', 'cashier'] as $role) {
            $this->db->table('users')->where('id', 1)->update(['role' => $role]);
            session()->set(['role' => $role, 'account_fingerprint' => \App\Libraries\AccountSession::fingerprint($this->row('users'))]);
            foreach ([$post, $post + $this->expiryYearOldOverride()] as $input) {
                $this->controller(\App\Controllers\Products::class, $input)->store();
                $this->assertSame($before, $this->expiryYearSnapshot());
                $this->assertArrayHasKey('expiration_date', session()->getFlashdata('errors'));
            }
        }
    }

    public function testExpiryYearRangeNewProductAcceptsBoundaryWithoutReviewAndPreservesExistingInventory(): void
    {
        $post = $this->expiryProductEditPost(['sku' => 'YEAR-RANGE-BOUNDARY', 'expiration_date' => $this->expiryYearMaximum()]);
        $this->db->query('ALTER TABLE branch_products ADD COLUMN created_at TEXT');
        $before = [$this->row('products'), $this->row('branch_products'), $this->row('sale_items')];
        $this->controller(\App\Controllers\Products::class, $post)->store();
        $created = $this->db->table('products')->where('sku', 'YEAR-RANGE-BOUNDARY')->get()->getRowArray();
        $this->assertNotNull($created, json_encode([session()->getFlashdata('error'), session()->getFlashdata('errors')]));
        $inventory = $this->db->table('branch_products')->where('product_id', $created['id'])->get()->getRowArray();
        $this->assertSame($this->expiryYearMaximum(), $inventory['expiration_date']);
        $this->assertSame(10, (int) $inventory['stock']);
        $this->assertSame($before, [$this->row('products'), $this->row('branch_products'), $this->row('sale_items')]);
        $audit = implode("\n", array_column($this->db->table('activity_logs')->get()->getResultArray(), 'activity'));
        $this->assertStringNotContainsString('Expiry range review', $audit);
    }

    public function testExpiryYearRangeExistingProductAssignmentCannotUseTheLegacyOverride(): void
    {
        $post = $this->expiryProductEditPost(['branch_id' => '2', 'stock' => '6']);
        $this->db->query('ALTER TABLE branch_products ADD COLUMN created_at TEXT');
        $this->db->table('branches')->insert(['id' => 2, 'branch_name' => 'Second Branch', 'status' => 'active']);
        $before = $this->expiryYearSnapshot();
        $this->controller(\App\Controllers\Products::class, $post + $this->expiryYearOldOverride())->store();
        $this->assertSame($before, $this->expiryYearSnapshot());
        $this->assertArrayHasKey('expiration_date', session()->getFlashdata('errors'));
    }

    public function testExpiryYearRangeExistingProductAssignmentAcceptsBoundaryWithoutReview(): void
    {
        $post = $this->expiryProductEditPost(['branch_id' => '2', 'stock' => '6', 'expiration_date' => $this->expiryYearMaximum()]);
        $this->db->query('ALTER TABLE branch_products ADD COLUMN created_at TEXT');
        $this->db->table('branches')->insert(['id' => 2, 'branch_name' => 'Second Branch', 'status' => 'active']);
        $original = [$this->row('branch_products'), $this->row('sale_items')];
        $this->controller(\App\Controllers\Products::class, $post)->store();
        $assigned = $this->db->table('branch_products')->where('branch_id', 2)->get()->getRowArray();
        $this->assertNotNull($assigned);
        $this->assertSame($this->expiryYearMaximum(), $assigned['expiration_date']);
        $this->assertSame(6, (int) $assigned['stock']);
        $this->assertSame(16, (int) $this->row('products')['stock']);
        $this->assertSame($original, [$this->row('branch_products'), $this->row('sale_items')]);
        $this->assertSame(1, $this->db->table('activity_logs')->countAllResults());
    }

    public function testExpiryYearRangeEditRejectsChangedDateAndForgedOriginalWithoutWrites(): void
    {
        $post = $this->expiryProductEditPost(['expiration_date' => '2099-02-01', 'expiry_original' => '2099-02-01', 'product_name' => 'Forged original', 'stock' => '12', 'stock_reason' => 'Changed count']);
        $before = $this->expiryYearSnapshot();
        $this->controller(\App\Controllers\Products::class, $post + $this->expiryYearOldOverride())->update(1);
        $this->assertSame($before, $this->expiryYearSnapshot());
        $this->assertArrayHasKey('expiration_date', session()->getFlashdata('errors'));
    }

    public function testExpiryYearRangeProductEditAllowsUnchangedHistoricalDates(): void
    {
        $post = $this->expiryProductEditPost(['product_name' => 'Changed medicine name']);
        foreach (['2000-01-01', '2099-01-01'] as $original) {
            $this->db->table('branch_products')->where('id', 1)->update(['expiration_date' => $original]);
            $this->controller(\App\Controllers\Products::class, array_replace($post, ['expiration_date' => $original]))->update(1);
            $this->assertSame('Changed medicine name', $this->row('products')['product_name']);
            $this->assertSame($original, $this->row('branch_products')['expiration_date']);
            $this->assertSame(10, (int) $this->row('branch_products')['stock']);
            $this->assertSame('2099-01-01', $this->row('sale_items')['expiration_date_at_sale']);
        }
    }

    public function testExpiryYearRangeProductEditAcceptsBoundaryWithoutRewritingSaleSnapshots(): void
    {
        $post = $this->expiryProductEditPost(['expiration_date' => $this->expiryYearMaximum()]);
        $item = $this->row('sale_items');
        $this->controller(\App\Controllers\Products::class, $post)->update(1);
        $this->assertSame($this->expiryYearMaximum(), $this->row('branch_products')['expiration_date']);
        $this->assertSame($item, $this->row('sale_items'));
        $this->assertSame(0, $this->db->table('stock_logs')->countAllResults());
        $this->assertSame(1, $this->db->table('activity_logs')->countAllResults());
    }

    public function testExpiryYearRangeCatalogRejectsLegacyOverrideButAcceptsBoundary(): void
    {
        $this->prepareCatalogBranch();
        $this->db->table('users')->where('id', 1)->update(['role' => 'cashier', 'branch_id' => 2]);
        session()->set('account_fingerprint', \App\Libraries\AccountSession::fingerprint($this->row('users')));
        $before = $this->expiryYearSnapshot();
        $this->controller(\App\Controllers\Products::class, $this->catalogInput(['expiration_date' => '2099-01-01']) + $this->expiryYearOldOverride())->addExisting();
        $this->assertSame($before, $this->expiryYearSnapshot());
        $this->assertStringContainsString($this->expiryYearMaximum(), session()->getFlashdata('error'));
        $this->controller(\App\Controllers\Products::class, $this->catalogInput(['expiration_date' => $this->expiryYearMaximum()]))->addExisting();
        $assigned = $this->db->table('branch_products')->where('branch_id', 2)->get()->getRowArray();
        $this->assertNotNull($assigned);
        $this->assertSame($this->expiryYearMaximum(), $assigned['expiration_date']);
        $this->assertSame(6, (int) $assigned['stock']);
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
        $this->assertSame(16, (int) $this->row('products')['stock']);
    }

    public function testExpiryYearRangeReplacementRejectsBothRolesEvenWhenDateMatchesOldRecord(): void
    {
        $service = new \App\Libraries\ExpiryStockResolution($this->db);
        $input = ['outcome' => 'supplier_return', 'new_quantity' => 20, 'new_expiry' => '2099-01-01', 'reason' => 'Supplier replacement delivery', 'confirmed' => '1'] + $this->expiryYearOldOverride();
        $record = $service->inventory(1, 'admin', 1); $revision = $service::revision($record);
        $before = $this->expiryYearSnapshot();
        foreach (['admin', 'cashier'] as $role) {
            $this->expiryYearRejected(fn () => $service->resolve(1, 'replace', $input, $role, 1, 1, str_repeat('a', 48), $revision));
            $this->assertSame($before, $this->expiryYearSnapshot());
        }
    }

    public function testExpiryYearRangeReplacementAcceptsBoundaryAndKeepsOriginalSaleHistory(): void
    {
        $service = new \App\Libraries\ExpiryStockResolution($this->db);
        $input = ['outcome' => 'supplier_return', 'new_quantity' => 20, 'new_expiry' => $this->expiryYearMaximum(), 'reason' => 'Supplier replacement delivery', 'confirmed' => '1'];
        $record = $service->inventory(1, 'cashier', 1); $revision = $service::revision($record);
        $item = $this->row('sale_items');
        $this->assertTrue($service->resolve(1, 'replace', $input, 'cashier', 1, 1, str_repeat('b', 48), $revision));
        $this->assertFalse($service->resolve(1, 'replace', $input, 'cashier', 1, 1, str_repeat('b', 48), $revision));
        $this->assertSame(20, (int) $this->row('branch_products')['stock']);
        $this->assertSame($this->expiryYearMaximum(), $this->row('branch_products')['expiration_date']);
        $this->assertNotSame($record['stock_delivery_id'], $this->row('branch_products')['stock_delivery_id']);
        $this->assertSame($item, $this->row('sale_items'));
        $this->assertSame(2, $this->db->table('stock_logs')->countAllResults());
        $this->assertSame(1, $this->db->table('activity_logs')->countAllResults());
    }

    public function testExpiryYearRangeReplacementRejectsWhitespaceAndStoresNormalizedBoundaryDate(): void
    {
        $service = new \App\Libraries\ExpiryStockResolution($this->db);
        $input = ['outcome' => 'supplier_return', 'new_quantity' => 20, 'reason' => 'Replacement delivery', 'confirmed' => '1'];
        $revision = $service::revision($service->inventory(1, 'admin', 1));
        $before = $this->expiryYearSnapshot();
        foreach (['', '   ', null, []] as $date) {
            $this->expiryYearRejected(fn () => $service->resolve(1, 'replace', $input + ['new_expiry' => $date], 'admin', 1, 1, str_repeat('f', 48), $revision));
            $this->assertSame($before, $this->expiryYearSnapshot());
        }
        $this->assertTrue($service->resolve(1, 'replace', $input + ['new_expiry' => ' ' . $this->expiryYearMaximum() . ' '], 'admin', 1, 1, str_repeat('f', 48), $revision));
        $this->assertSame($this->expiryYearMaximum(), $this->row('branch_products')['expiration_date']);
    }
}
