<?php

/** The owner-selected policy expires stock at the start of its recorded date. */
trait ExpiryCutoffCases
{
    private function expiryCutoffDate(int $days = 0): string
    {
        return date('Y-m-d', strtotime(($days >= 0 ? '+' : '') . $days . ' days'));
    }

    private function expiryCutoffArtifacts(): string
    {
        $dir = WRITEPATH . 'expiry-date-cutoff/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        return $dir;
    }

    public function testExpiryCutoffReportFiltersLabelsAndSummaryAgreeOnBoundaryDates(): void
    {
        $this->retainedModels();
        $controller = $this->controller(\App\Controllers\Admin\ReportsController::class, []);
        $today = $this->expiryCutoffDate(); $near = $this->expiryCutoffDate(30);
        $builder = new ReflectionMethod($controller, 'expiryProductsBuilder');
        $label = new ReflectionMethod($controller, 'expiryStatusLabel');
        $summary = new ReflectionMethod($controller, 'expirySummary');
        foreach ([[-1, 'expired', 'Expired'], [0, 'expired', 'Expired'], [1, 'near', 'Near Expiry'], [30, 'near', 'Near Expiry'], [31, 'good', 'Good'], [null, 'missing', 'No Expiry Date']] as [$days, $status, $name]) {
            $expiry = $days === null ? null : $this->expiryCutoffDate($days);
            $this->db->table('branch_products')->where('id', 1)->update(['expiration_date' => $expiry]);
            $this->assertSame($name, $label->invoke($controller, $expiry, $today, $near));
            foreach (['expired', 'near', 'good', 'missing'] as $filter) {
                $rows = $builder->invoke($controller, '1', $filter, $today, $near)->get()->getResultArray();
                $this->assertCount($filter === $status ? 1 : 0, $rows, 'Wrong filter for expiry ' . ($expiry ?? 'none'));
            }
            $counts = $summary->invoke($controller, [['expiration_date' => $expiry]], $today, $near);
            $this->assertSame(1, $counts[$status]);
        }
    }

    public function testExpiryCutoffProductAndExpiryScreensShowTodayAsExpired(): void
    {
        helper(['url', 'form', 'inflector']); $this->retainedModels();
        foreach ([0 => 'Expired', 1 => 'Near'] as $days => $label) {
            $this->db->table('branch_products')->where('id', 1)->update(['expiration_date' => $this->expiryCutoffDate($days)]);
            \Config\Services::resetSingle('renderer');
            $html = $this->controller(\App\Controllers\Products::class, [])->index();
            $doc = new DOMDocument(); @$doc->loadHTML($html); $xp = new DOMXPath($doc);
            $this->assertSame($label, trim($xp->evaluate('string(//tbody/tr[1]/td[12]//span[contains(@class,"badge")])')));
            if ($days === 0) file_put_contents($this->expiryCutoffArtifacts() . 'products-today.html', $html);
        }
        $this->db->table('branch_products')->where('id', 1)->update(['expiration_date' => $this->expiryCutoffDate()]);
        \Config\Services::resetSingle('renderer');
        $html = $this->controller(\App\Controllers\Admin\ReportsController::class, [])->expiryReport();
        $doc = new DOMDocument(); @$doc->loadHTML($html); $xp = new DOMXPath($doc);
        $this->assertSame('Expired', trim($xp->evaluate('string(//tbody/tr[1]/td[8])')));
        $dir = $this->expiryCutoffArtifacts();
        file_put_contents($dir . 'expiry-report.html', $html);
    }

    public function testExpiryCutoffPosSearchExcludesTodayButAllowsTomorrowAndNoExpiry(): void
    {
        foreach ([0 => false, 1 => true, 2 => true] as $days => $allowed) {
            $expiry = $days === 2 ? null : $this->expiryCutoffDate($days);
            $this->db->table('branch_products')->where('id', 1)->update(['expiration_date' => $expiry]);
            $html = $this->controller(\App\Controllers\Cashier\SalesController::class, [])->searchProducts();
            $this->assertSame($allowed, str_contains($html, 'Test Item'));
        }
    }

    public function testExpiryCutoffPosAjaxRejectsAddingAndUpdatingTodayStock(): void
    {
        $this->posAjaxSetup();
        $this->db->table('branch_products')->where('id', 1)->update(['expiration_date' => $this->expiryCutoffDate()]);
        [$status, $body] = $this->posAjax('addToCart', ['product_id' => 1, 'quantity' => 1]);
        $this->assertSame(422, $status); $this->assertStringContainsString('expired', strtolower($body['message']));
        $this->db->table('branch_products')->where('id', 1)->update(['expiration_date' => $this->expiryCutoffDate(1)]);
        [$status] = $this->posAjax('addToCart', ['product_id' => 1, 'quantity' => 1]); $this->assertSame(200, $status);
        $before = session('cart');
        $this->db->table('branch_products')->where('id', 1)->update(['expiration_date' => $this->expiryCutoffDate()]);
        [$status, $body] = $this->posAjax('updateCart', ['product_id' => 1, 'quantity' => 2]);
        $this->assertSame(422, $status); $this->assertSame($before, session('cart'));
        $this->assertStringContainsString('expired', strtolower($body['message']));
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
    }

    public function testExpiryCutoffCheckoutRejectsAnExistingCartWithoutFinancialWrites(): void
    {
        $this->db->table('branch_products')->where('id', 1)->update(['expiration_date' => $this->expiryCutoffDate()]);
        $token = str_repeat('a', 32); session()->set(['cart' => [1 => ['product_id' => 1, 'quantity' => 1]], 'checkout_token' => $token]);
        $this->controller(\App\Controllers\Cashier\SalesController::class, ['_checkout_token' => $token, 'items' => [1 => 1], 'payment_method' => 'cash', 'amount_paid' => '10'])->checkout();
        $this->assertSame(1, $this->db->table('sales')->countAllResults());
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
        $this->assertSame(0, $this->db->table('stock_logs')->countAllResults());
        $this->assertStringContainsString('expired', strtolower((string) session()->getFlashdata('error')));
    }

    public function testExpiryCutoffCheckoutRechecksTheLockedExpiryAfterCartValidation(): void
    {
        $token = str_repeat('a', 32); session()->set(['cart' => [1 => ['product_id' => 1, 'quantity' => 1]], 'checkout_token' => $token]);
        $this->db->beforeInventoryLock = function ($db) { $db->table('branch_products')->where('id', 1)->update(['expiration_date' => $this->expiryCutoffDate()]); };
        $this->controller(\App\Controllers\Cashier\SalesController::class, ['_checkout_token' => $token, 'items' => [1 => 1], 'payment_method' => 'cash', 'amount_paid' => '10'])->checkout();
        $this->assertNull($this->db->beforeInventoryLock, 'The inventory lock hook must execute.');
        $this->assertStringContainsString('expired', strtolower((string) session()->getFlashdata('error')));
        $this->assertSame(1, $this->db->table('sales')->countAllResults());
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
    }

    public function testExpiryCutoffResellableReturnsRejectTodayWhileOtherConditionsRemainAvailable(): void
    {
        foreach (['expired', 'damaged', 'quarantined'] as $condition) $this->assertSame(0, \App\Libraries\ReturnCondition::restockQuantity($condition, 1, '2026-10-23', '2026-10-23'));
        $this->assertSame(1, \App\Libraries\ReturnCondition::restockQuantity('resellable', 1, '2026-10-24', '2026-10-23'));
        $this->expectException(InvalidArgumentException::class);
        \App\Libraries\ReturnCondition::restockQuantity('resellable', 1, '2026-10-23', '2026-10-23');
    }

    public function testExpiryCutoffRefundBlocksResellableButAllowsQuarantinedPayout(): void
    {
        $this->db->table('branch_products')->where('id', 1)->update(['expiration_date' => $this->expiryCutoffDate()]);
        session()->set('refund_token_1', 'expiry-cutoff');
        $post = ['_refund_token' => 'expiry-cutoff', 'reason' => 'Expired return review', 'refund_method' => 'cash', 'refund' => [1 => 1], 'return_condition' => [1 => 'resellable']];
        $this->controller(\App\Controllers\Cashier\SalesController::class, $post)->refundPartial(1);
        $this->assertSame(0, $this->db->table('refund_items')->countAllResults());
        $this->assertStringContainsString('Expired', (string) session()->getFlashdata('error'));
        session()->set('refund_token_1', 'expiry-cutoff'); $post['return_condition'] = [1 => 'quarantined'];
        $this->controller(\App\Controllers\Cashier\SalesController::class, $post)->refundPartial(1);
        $this->assertSame(1, $this->db->table('refund_items')->countAllResults());
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
    }

    public function testExpiryCutoffExchangeRejectsTodayReplacementAtReviewAndConfirmation(): void
    {
        $input = $this->exchangeInput(); $service = new \App\Libraries\ProductExchange($this->db);
        $this->db->table('branch_products')->where('id', 100)->update(['expiration_date' => $this->expiryCutoffDate()]);
        try { $service->quote(1, $input, 1, 'admin'); $this->fail('Today replacement was accepted.'); }
        catch (InvalidArgumentException $e) { $this->assertStringContainsString('expired', $e->getMessage()); }
        $this->db->table('branch_products')->where('id', 100)->update(['expiration_date' => $this->expiryCutoffDate(1)]);
        $quote = $service->quote(1, $input, 1, 'admin');
        $this->db->table('branch_products')->where('id', 100)->update(['expiration_date' => $this->expiryCutoffDate()]);
        try { $service->complete(1, $input, 1, 'admin', 1, str_repeat('f', 48), $quote['revision']); $this->fail('Expired replacement confirmation was accepted.'); }
        catch (InvalidArgumentException $e) { $this->assertMatchesRegularExpression('/expired|changed/i', $e->getMessage()); }
        $this->assertSame(1, $this->db->table('sales')->countAllResults());
        $this->assertSame(0, $this->db->table('refund_items')->countAllResults());
    }

    public function testExpiryCutoffHeldCartRemainsHeldWhenItsStockExpiresToday(): void
    {
        $service = $this->cashierSetup();
        $id = $service->hold(1, 1, [1 => ['product_id' => 1, 'quantity' => 1]], 'Expiry check');
        $this->db->table('branch_products')->where('id', 1)->update(['expiration_date' => $this->expiryCutoffDate()]);
        try { $service->takeHeld(1, 1, $id, false, true); $this->fail('Held expired cart resumed.'); }
        catch (InvalidArgumentException $e) { $this->assertStringContainsString('expired', $e->getMessage()); }
        $this->assertSame(1, $this->db->table('cashier_held_sales')->countAllResults());
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
    }

    public function testExpiryCutoffQuantityCorrectionCannotIncreaseTodayStockSales(): void
    {
        $this->db->table('branch_products')->where('id', 1)->update(['expiration_date' => $this->expiryCutoffDate()]);
        $this->db->table('sale_items')->where('id', 1)->update(['expiration_date_at_sale' => $this->expiryCutoffDate()]);
        $result = $this->controller(\App\Controllers\Admin\SaleCorrection::class, $this->correctionPost(['items' => [1 => 6]]))->update(1);
        $this->assertSame(5, (int) $this->row('sale_items')['quantity']);
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
        $this->assertStringContainsString('Expired inventory', is_string($result) ? $result : $result->getBody());
    }

    public function testExpiryCutoffQuarantineCannotReleaseTodayStockButCanMarkItExpired(): void
    {
        $service = $this->quarantineFixture();
        foreach (['branch_products' => 'expiration_date', 'sale_items' => 'expiration_date_at_sale'] as $table => $field) $this->db->table($table)->where('id', 1)->update([$field => $this->expiryCutoffDate()]);
        $record = $service->item(1, 1); $revision = \App\Libraries\Quarantine::revision($record);
        $this->assertStringContainsString('Expired', \App\Libraries\Quarantine::releaseProblem($record));
        try { $service->resolve(1, 1, $this->quarantineInput(), $revision); $this->fail('Today stock was released.'); }
        catch (InvalidArgumentException $e) { $this->assertStringContainsString('Expired', $e->getMessage()); }
        $this->assertSame(0, $this->db->table('quarantine_resolutions')->countAllResults());
        $this->assertTrue($service->resolve(1, 1, $this->quarantineInput('expired'), $revision));
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
        $this->assertSame(0, $this->db->table('stock_logs')->countAllResults());
    }

    public function testExpiryCutoffNewInventoryRejectsTodayExpiry(): void
    {
        $fixtures = $this->managementFixtures(); $post = $fixtures['product'][3];
        $post = array_replace($post, ['product_name' => 'New expiry test', 'sku' => 'NEW-EXPIRY', 'expiration_date' => $this->expiryCutoffDate()]);
        $this->controller(\App\Controllers\Products::class, $post)->store();
        $this->assertSame(1, $this->db->table('products')->countAllResults());
        $this->assertNotEmpty(session()->getFlashdata('errors')['expiration_date']);
    }

    public function testExpiryCutoffNotificationsCountTodayAsExpiredAndTomorrowAsNear(): void
    {
        $this->db->table('branch_products')->where('id', 1)->update(['reorder_level' => 0]);
        $service = new \App\Libraries\TopbarNotificationService($this->db);
        foreach ([0 => 'expired', 1 => 'near'] as $days => $status) {
            $this->db->table('branch_products')->where('id', 1)->update(['expiration_date' => $this->expiryCutoffDate($days)]);
            $summary = $service->getSummary('admin');
            $items = array_values(array_filter($summary['items'], static fn ($i) => str_contains($i['url'], 'status=' . $status)));
            $this->assertCount(1, $items); $this->assertSame(1, $items[0]['count']);
        }
    }
    public function testExpiryCutoffPdfListsTodayWithExpiredAndTomorrowWithNear(): void
    {
        $products = []; $today = $this->expiryCutoffDate(); $near = $this->expiryCutoffDate(30);
        foreach ([-1, 0, 1, 30, 31, null] as $index => $days) $products[] = [
            'id' => $index + 1, 'product_id' => $index + 1, 'product_name' => 'Expiry boundary ' . $index,
            'sku' => 'EXP-' . $index, 'branch_name' => 'Boundary Test Branch', 'unit' => 'tablet', 'stock' => 10,
            'expiration_date' => $days === null ? null : $this->expiryCutoffDate($days),
        ];
        $controller = $this->controller(\App\Controllers\Admin\ReportsController::class, []);
        $summary = (new ReflectionMethod($controller, 'expirySummary'))->invoke($controller, $products, $today, $near);
        $context = \App\Libraries\ReportPdf::context('Expiry Report', 'Boundary Test Branch', 'Expiry cutoff boundary test', 'Test Staff');
        $html = view('admin/reports/expiry_report_pdf', $context + compact('products', 'today', 'summary') + ['nearDate' => $near, 'nearExpiryDays' => 30, 'filterStatusLabel' => 'All Statuses'], ['saveData' => false]);
        $doc = new DOMDocument(); @$doc->loadHTML($html); $xp = new DOMXPath($doc);
        $statuses = [];
        foreach ($xp->query('(//table[contains(@class,"data")])[1]/tbody/tr/td[9]') as $td) $statuses[] = trim($td->textContent);
        $this->assertSame(['Expired', 'Expired', 'Near expiry', 'Near expiry', 'Good', 'No expiry date'], $statuses);
        $this->assertSame(2, $summary['expired']); $this->assertSame(2, $summary['near']);
        file_put_contents($this->expiryCutoffArtifacts() . 'pdf-fixture.html', $html);
        file_put_contents($this->expiryCutoffArtifacts() . 'pdf-data.json', json_encode($context + compact('products', 'today', 'summary') + ['nearDate' => $near, 'nearExpiryDays' => 30, 'filterStatusLabel' => 'All Statuses'], JSON_THROW_ON_ERROR));
    }

}
